<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Listener;

use SplFileInfo;
use Imagine\Image\Box;
use Imagine\Gd\Imagine;
use Vich\UploaderBundle\Event\Event;
use Vich\UploaderBundle\Event\Events;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Filesystem\Filesystem;
use c975L\SiteBundle\Entity\ArticleMedia;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsEventListener(event: 'vich_uploader.post_upload', method: 'onPostUpload')]
class VichImageResizeListener implements EventSubscriberInterface
{
    private Filesystem $filesystem;

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly EntityManagerInterface $entityManager,
    ) {
        $this->filesystem = new Filesystem();
    }

    public static function getSubscribedEvents(): array
    {
        return [
            Events::POST_UPLOAD => 'onPostUpload',
        ];
    }

    public function onPostUpload(Event $event)
    {
        $entity = $event->getObject();
        $mapping = $event->getMapping();
        $filename = $mapping->getFileName($entity);
        $absolutePath = $this->parameterBag->get('kernel.project_dir') . '/public/' . $filename;

        if (!$this->filesystem->exists($absolutePath)) {
            return;
        }

        $extension = $entity->getFile()->getExtension();

        // Process images
        if (in_array($extension, ['jpg', 'png', 'gif', 'webp'])) {
            $this->processImage($entity, $absolutePath);

            return;
        }
    }

    // Resize and save the image
    private function processImage($entity, string $absolutePath): void
    {
        // Gets the width for the entity
        $width = 800;

        // Resizes the image
        $format = 'webp';
        $imagine = new Imagine();
        $media = $imagine->open($absolutePath);
        $size = $media->getSize();
        $height = (int) ($size->getHeight() * $width / $size->getWidth());

        $media
            ->resize(new Box($width, $height))
            ->save($absolutePath, [
                'format' => $format,
                'webp_quality' => 90,
            ]);

        $this->updateEntitySize($entity, $absolutePath);
    }

    // Updates the size of the entity
    private function updateEntitySize($entity, $filePath): void
    {
        if (method_exists($entity, 'setSize')) {
            $file = new SplFileInfo($filePath);
            $entity->setSize($file->getSize());
        }
    }
}