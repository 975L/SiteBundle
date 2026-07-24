<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\ExportProviderInterface;
use c975L\SiteBundle\Management\Trait\ArchiveFileTrait;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\MediaRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Serializes site-wide graphics (favicon, apple-touch-icon, og-image, logo, error-image pool - see SiteGraphicCrudController) into the shape ContentExporter/SiteGraphicImportProvider expect, for the "export sync all" dashboard shortcut (see ConfigBundle's SyncAllExporter). These Media rows carry a "role" instead of being attached to a Block (see Media::getRole()), so they're invisible to PageExportProvider's own Block->Media traversal
class SiteGraphicExportProvider implements ExportProviderInterface
{
    use ArchiveFileTrait;

    public function __construct(
        private readonly MediaRepository $mediaRepository,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function getKind(): string
    {
        return SiteGraphicImportProvider::KIND;
    }

    public function exportAll(): array
    {
        $graphics = $this->mediaRepository->createQueryBuilder('m')
            ->where('m.role IS NOT NULL')
            ->orderBy('m.role', 'ASC')
            ->addOrderBy('m.id', 'ASC')
            ->getQuery()
            ->getResult();

        return $this->serialize($graphics);
    }

    // @param iterable<Media> $graphics
    public function serialize(iterable $graphics): array
    {
        $files = [];
        $items = [];
        foreach ($graphics as $media) {
            $data = $this->exportGraphicData($media, $files);
            if (null !== $data) {
                $items[] = $data;
            }
        }

        return ['items' => $items, 'files' => $files];
    }

    // Registers the graphic's physical file for the zip archive (&$files: archive-relative path => disk path), returning the metadata entry with a 'file' reference instead of embedding its bytes - same convention as FontExportProvider::exportFontData(). A role with no readable file is skipped entirely: there's nothing meaningful to re-import
    private function exportGraphicData(Media $media, array &$files): ?array
    {
        $filename = $media->getFilename();
        if (null === $filename) {
            return null;
        }

        $registered = $this->registerArchiveFile($this->projectDir, $filename, $files);
        if (null === $registered) {
            return null;
        }

        return [
            'role' => $media->getRole(),
            'originalFilename' => $registered['originalFilename'],
            'file' => $registered['archivePath'],
        ];
    }
}
