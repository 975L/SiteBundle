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

        foreach ($blockData['medias'] ?? [] as $mediaData) {
            $media = $this->buildMedia($mediaData, $filesDir);
            $this->em->persist($media);
            $block->addMedia($media);
        }

        foreach ($blockData['slots'] ?? [] as $slotData) {
            $slot = $this->buildBlock($slotData, $filesDir);
            $this->em->persist($slot);
            $block->addSlot($slot);
        }

        $this->em->persist($block);

        return $block;
    }

    // Rebuilds a Media from its exported metadata, its file read straight from the extracted zip archive (see ContentImportController) and run through Vich's normal upload pipeline via ReplacingFile (a plain File is silently ignored by Vich's UploadHandler, see PageCrudController::cloneMedia()), so filename/size/mimeType/thumbnails all get regenerated here rather than trusting the exporting environment's values. Public: also used directly for a standalone Media not attached to any Block (eg. Page::$ogImage)
    public function buildMedia(array $mediaData, ?string $filesDir): Media
    {
        $media = (new Media())
            ->setRole($mediaData['role'] ?? null)
            ->setAlt($mediaData['alt'] ?? null)
            ->setLabel($mediaData['label'] ?? null)
            ->setWidth($mediaData['width'] ?? null)
            ->setHeight($mediaData['height'] ?? null)
            ->setCssClasses($mediaData['cssClasses'] ?? null)
            ->setAbove($mediaData['above'] ?? false)
            ->setCredits($mediaData['credits'] ?? null)
            ->setRightsReserved($mediaData['rightsReserved'] ?? false)
            ->setPosition($mediaData['position'] ?? 0)
            ->setUrl($mediaData['url'] ?? null)
            ->setDescription($mediaData['description'] ?? null);

        if (null !== $filesDir && isset($mediaData['file'])) {
            $media->setFile(new ReplacingFile($filesDir . '/' . $mediaData['file'], true, true, true));
        }

        return $media;
    }
}
