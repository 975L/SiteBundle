<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\SiteBundle\Service\DefaultPagesImporter;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use Doctrine\ORM\EntityManagerInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// Shared Block/Media rebuild for every Sync import carrying a Block collection (Page, Menu) - mirrors BlockDataExporter on the way in
class BlockDataImporter
{
    // Every scalar Media field an export carries, with the value to fall back on when the archive predates that field - keeps buildMedia() a plain mapping instead of a chain of thirteen "?? default"
    private const MEDIA_DEFAULTS = [
        'role' => null,
        'name' => null,
        'alt' => null,
        'label' => null,
        'width' => null,
        'height' => null,
        'cssClasses' => null,
        'above' => false,
        'credits' => null,
        'rightsReserved' => false,
        'position' => 0,
        'url' => null,
        'description' => null,
    ];

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly DefaultPagesImporter $defaultPagesImporter,
    ) {
    }

    // @return Block[]
    public function buildBlocks(array $blocksData, ?string $filesDir): array
    {
        $blocks = [];
        foreach ($blocksData as $blockData) {
            $blocks[] = $this->buildBlock($blockData, $filesDir);
        }

        return $blocks;
    }

    // A container kind's (eg. flex_columns) slots are themselves full Blocks - own kind/data/medias/slots (see Block::getSlots()/BlockDataExporter::exportBlockData()) - recursed into here so a block nested in a container round-trips like a top-level one
    private function buildBlock(array $blockData, ?string $filesDir): Block
    {
        $this->defaultPagesImporter->ensureFormBlockDependenciesExist($blockData);

        $block = (new Block())
            ->setKind($blockData['kind'])
            ->setPosition($blockData['position'])
            ->setData($blockData['data'] ?? [])
            ->setAnimation($blockData['animation'] ?? null);

        $this->addMedias($block, $blockData['medias'] ?? [], $filesDir);

        // buildBlock() persists the slot itself, at the end of its own recursion
        foreach ($blockData['slots'] ?? [] as $slotData) {
            $block->addSlot($this->buildBlock($slotData, $filesDir));
        }

        $this->em->persist($block);

        return $block;
    }

    private function addMedias(Block $block, array $mediasData, ?string $filesDir): void
    {
        foreach ($mediasData as $mediaData) {
            $media = $this->buildMedia($mediaData, $filesDir);
            $this->em->persist($media);
            $block->addMedia($media);
        }
    }

    // Rebuilds a Media from its exported metadata, its file read straight from the extracted zip archive (see ContentImportController) and run through Vich's normal upload pipeline via ReplacingFile (a plain File is silently ignored by Vich's UploadHandler, see PageCrudController::cloneMedia()), so filename/size/mimeType/thumbnails all get regenerated here rather than trusting the exporting environment's values. Public: also used directly for a standalone Media not attached to any Block (eg. Page::$ogImage)
    public function buildMedia(array $mediaData, ?string $filesDir): Media
    {
        $values = [];
        foreach (self::MEDIA_DEFAULTS as $key => $default) {
            $values[$key] = $mediaData[$key] ?? $default;
        }

        $media = (new Media())
            ->setRole($values['role'])
            ->setName($values['name'])
            ->setAlt($values['alt'])
            ->setLabel($values['label'])
            ->setWidth($values['width'])
            ->setHeight($values['height'])
            ->setCssClasses($values['cssClasses'])
            ->setAbove($values['above'])
            ->setCredits($values['credits'])
            ->setRightsReserved($values['rightsReserved'])
            ->setPosition($values['position'])
            ->setUrl($values['url'])
            ->setDescription($values['description']);

        if (null !== $filesDir && isset($mediaData['file'])) {
            $media->setFile(new ReplacingFile($filesDir . '/' . $mediaData['file'], true, true, true));
        }

        // Read by VichPdfThumbnailListener on flush, so it reuses this thumbnail instead of Ghostscript
        if (null !== $filesDir && isset($mediaData['thumbnail'])) {
            $media->setImportedThumbnailPath($filesDir . '/' . $mediaData['thumbnail']);
        }

        return $media;
    }
}
