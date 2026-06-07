<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Namer;

use c975L\SiteBundle\Entity\ArticleMedia;
use RuntimeException;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\PropertyMapping;
use Vich\UploaderBundle\Naming\NamerInterface;

class SiteMediaNamer implements NamerInterface
{
    const SITE_ROOT = 'medias/site/';

    private Filesystem $filesystem;

    public function __construct(
        private readonly ParameterBagInterface $parameterBag
    ) {
        $this->filesystem = new Filesystem();
    }

    public function name($entity, PropertyMapping $mapping): string
    {
        $filePath = $entity->getFile()->getPathname();

        if (!$this->filesystem->exists($filePath)) {
            throw new RuntimeException('File not found: ' . htmlspecialchars($filePath, ENT_QUOTES, 'UTF-8'));
        }
        $file = $mapping->getFile($entity);
        $extension = $this->determineExtension($file);
        $filename = $this->getEntityPath($entity);

        return self::SITE_ROOT . $filename . '-' . uniqid() . '.' . $extension;
    }


    // Determine file extension based on mime type
    private function determineExtension(File $file): string
    {
        $mimeType = $file->getMimeType();
        $extension = $file->getExtension();

        // Determine the extension based on mime type
        if ('image/jpeg' === $mimeType || 'image/jpg' === $mimeType) {
            $extension = 'jpg';
        } elseif ('image/png' === $mimeType) {
            $extension = 'png';
        } elseif ('image/gif' === $mimeType) {
            $extension = 'gif';
        } elseif ('image/webp' === $mimeType) {
            $extension = 'webp';
        }

        // Converts to webp if the mime type is an image
        if (in_array($extension, ['jpg', 'png', 'gif', 'webp'])) {
            return 'webp';
        }

        // Fallback to the original extension
        return $extension ?: $file->getClientOriginalExtension();
    }

    // Get path segment based on entity type
    private function getEntityPath($entity): string
    {
    if ($entity instanceof ArticleMedia) {
            return $entity->getArticle()->getSlug();
        }
    }
}