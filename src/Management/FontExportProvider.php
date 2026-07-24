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
use c975L\SiteBundle\Entity\Font;
use c975L\SiteBundle\Management\Trait\ArchiveFileTrait;
use c975L\SiteBundle\Repository\FontRepository;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Serializes Fonts (name/weight/style + the real file bundled in the archive) into the shape ContentExporter/FontImportProvider expect - shared by FontCrudController::exportSelection() (a checked subset) and exportAll() below (every Font, for the "export sync all" dashboard shortcut, see ConfigBundle's SyncAllExporter)
class FontExportProvider implements ExportProviderInterface
{
    use ArchiveFileTrait;

    public function __construct(
        private readonly FontRepository $fontRepository,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function getKind(): string
    {
        return FontImportProvider::KIND;
    }

    public function exportAll(): array
    {
        return $this->serialize($this->fontRepository->findAllOrdered());
    }

    // @param iterable<Font> $fonts
    public function serialize(iterable $fonts): array
    {
        $files = [];
        $items = [];
        foreach ($fonts as $font) {
            $fontData = $this->exportFontData($font, $files);
            if (null !== $fontData) {
                $items[] = $fontData;
            }
        }

        return ['items' => $items, 'files' => $files];
    }

    // Registers the font's physical file for the zip archive (&$files: archive-relative path => disk path), returning the metadata entry with a 'file' reference instead of embedding its bytes - same convention as PageExportProvider::exportMediaData(). Returns null (skipped by the caller) when the file can't be read, rather than exporting a broken reference
    private function exportFontData(Font $font, array &$files): ?array
    {
        $filename = $font->getFilename();
        if (null === $filename) {
            return null;
        }

        $registered = $this->registerArchiveFile($this->projectDir, $filename, $files);
        if (null === $registered) {
            return null;
        }

        return [
            'name' => $font->getName(),
            'weight' => $font->getWeight(),
            'style' => $font->getStyle(),
            'originalFilename' => $registered['originalFilename'],
            'file' => $registered['archivePath'],
        ];
    }
}
