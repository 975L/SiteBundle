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
use c975L\SiteBundle\Repository\PageRepository;

// Serializes Pages (title/slug/summary/ogImage/Blocks, Media files bundled in the archive) into the shape ContentExporter/PageImportProvider expect - shared by PageCrudController::exportSelection() (a checked subset) and exportAll() below (every Page, for the "export sync all" dashboard shortcut, see ConfigBundle's SyncAllExporter)
class PageExportProvider implements ExportProviderInterface
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly BlockDataExporter $blockDataExporter,
    ) {
    }

    public function getKind(): string
    {
        return PageImportProvider::KIND;
    }

    // Drafts are included too (unlike PageRepository::findAllOrdered(), built for public display) - a prod sync shouldn't silently drop work in progress
    public function exportAll(): array
    {
        return $this->serialize($this->pageRepository->findBy(['isDeleted' => false]));
    }

    // @param iterable<Page> $pages
    public function serialize(iterable $pages): array
    {
        $files = [];
        $items = [];
        foreach ($pages as $page) {
            $ogImage = $page->getOgImage();

            $items[] = [
                'title' => $page->getTitle(),
                'slug' => $page->getSlug(),
                'changeFrequency' => $page->getChangeFrequency(),
                'priority' => $page->getPriority(),
                'isPublished' => $page->isPublished(),
                'summarySocialNetwork' => $page->getSummarySocialNetwork(),
                'ogImage' => null !== $ogImage ? $this->blockDataExporter->exportMedia($ogImage, $files) : null,
                'blocks' => $this->blockDataExporter->exportBlocks($page->getBlocks(), $files),
            ];
        }

        return ['items' => $items, 'files' => $files];
    }
}
