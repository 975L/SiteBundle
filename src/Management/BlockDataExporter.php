<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management;

use c975L\SiteBundle\Management\Trait\ArchiveFileTrait;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Shared Block/Media serialization for every Sync export carrying a Block collection (Page, Menu) - keeps the recursive container-slot walk in one place instead of duplicated per entity. Mirrors BlockDataImporter on the way back in
class BlockDataExporter
{
    use ArchiveFileTrait;

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    // @param iterable<Block> $blocks
    public function exportBlocks(iterable $blocks, array &$files): array
    {
        $data = [];
        foreach ($blocks as $block) {
            $data[] = $this->exportBlockData($block, $files);
        }

        return $data;
    }

    // A container kind's (eg. flex_columns) slots are themselves full Blocks - own kind/data/medias/slots (see Block::getSlots()) - recursed into here so a block nested in a container isn't silently dropped from the export
    private function exportBlockData(Block $block, array &$files): array
    {
        $medias = [];
        foreach ($block->getMedias() as $media) {
            $mediaData = $this->exportMedia($media, $files);
            if (null !== $mediaData) {
                $medias[] = $mediaData;
            }
        }

        $slots = [];
        foreach ($block->getSlots() as $slot) {
            $slots[] = $this->exportBlockData($slot, $files);
        }

        return [
            'kind' => $block->getKind(),
            'position' => $block->getPosition(),
            'data' => $block->getData(),
            'animation' => $block->getAnimation(),
            'medias' => $medias,
            'slots' => $slots,
        ];
    }

    // Reads the Media's physical file from disk and registers it for the zip archive (&$files: archive-relative path => disk path), returning the metadata entry with a 'file' reference instead of embedding its bytes - same disk-path convention as PageCrudController::cloneMedia(). Returns null (skipped by the caller) when there is no file or it can't be read, rather than exporting a broken reference. Public: also used directly for a standalone Media not attached to any Block (eg. Page::$ogImage)
    public function exportMedia(Media $media, array &$files): ?array
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
            'alt' => $media->getAlt(),
            'label' => $media->getLabel(),
            'width' => $media->getWidth(),
            'height' => $media->getHeight(),
            'cssClasses' => $media->getCssClasses(),
            'above' => $media->isAbove(),
            'credits' => $media->getCredits(),
            'rightsReserved' => $media->isRightsReserved(),
            'position' => $media->getPosition(),
            'url' => $media->getUrl(),
            'description' => $media->getDescription(),
            'originalFilename' => $registered['originalFilename'],
            'file' => $registered['archivePath'],
        ];
    }
}
