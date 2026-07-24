<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\ImportProviderInterface;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// Imports a "site_graphic" content export (see SiteGraphicExportProvider) - a singleton role (favicon, apple-touch-icon, og-image, logo) matches by its own role, the one natural key it has. The repeatable "error-image" role has none (several rows share it, forming a pool with nothing else distinguishing them), so re-importing replaces its whole pool instead of piling duplicates on top of whatever already exists
class SiteGraphicImportProvider implements ImportProviderInterface
{
    public const KIND = 'site_graphic';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MediaRepository $mediaRepository,
    ) {
    }

    public function supportsImport(string $kind): bool
    {
        return self::KIND === $kind;
    }

    public function import(array $items, ?string $filesDir = null): array
    {
        $created = 0;
        $updated = 0;
        $clearedRepeatableRoles = [];

        foreach ($items as $item) {
            $role = $item['role'];

            if (in_array($role, Media::getSingletonRoles(), true)) {
                $media = $this->mediaRepository->findOneByRole($role);
                $isNew = null === $media;
                $media ??= (new Media())->setRole($role);
            } else {
                if (!isset($clearedRepeatableRoles[$role])) {
                    foreach ($this->mediaRepository->findBy(['role' => $role]) as $existing) {
                        $this->em->remove($existing);
                    }
                    $clearedRepeatableRoles[$role] = true;
                }
                $media = (new Media())->setRole($role);
                $isNew = true;
            }

            if (null !== $filesDir && isset($item['file'])) {
                $media->setFile(new ReplacingFile($filesDir . '/' . $item['file'], true, true, true));
            }

            $this->em->persist($media);
            $isNew ? $created++ : $updated++;
        }

        $this->em->flush();

        return ['created' => $created, 'updated' => $updated];
    }
}
