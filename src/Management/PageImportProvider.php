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
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use Doctrine\ORM\EntityManagerInterface;

// Imports a "site_page" content export (see PageCrudController::exportSelection/ContentExporter) - unlike DefaultPagesImporter::import() (which only ever creates the fixed default pages and skips ones that already exist), this always overwrites: the whole point is pushing a page built in dev on top of whatever exists in prod under the same slug. Matches by slug, never by id (which won't match between environments) - Block has no natural key of its own, so its entire collection is replaced rather than diffed
class PageImportProvider implements ImportProviderInterface
{
    public const KIND = 'site_page';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PageRepository $pageRepository,
        private readonly BlockDataImporter $blockDataImporter,
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
        $now = new \DateTime();

        foreach ($items as $item) {
            $this->importPage($item, $now, $filesDir) ? $created++ : $updated++;
        }

        $this->em->flush();

        return ['created' => $created, 'updated' => $updated];
    }

    // One exported page written over whatever already lives under its slug - returns whether it had to be created
    private function importPage(array $item, \DateTime $now, ?string $filesDir): bool
    {
        $page = $this->pageRepository->findOneBy(['slug' => $item['slug']]);
        $isNew = null === $page;
        $page ??= (new Page())->setCreation($now);

        $this->fillPage($page, $item, $now);
        $this->replaceBlocks($page, $item['blocks'] ?? [], $filesDir);
        $this->replaceOgImage($page, $item['ogImage'] ?? null, $filesDir);

        $this->em->persist($page);

        return $isNew;
    }

    private function fillPage(Page $page, array $item, \DateTime $now): void
    {
        $page
            ->setTitle($item['title'])
            ->setSlug($item['slug'])
            ->setChangeFrequency($item['changeFrequency'] ?? null)
            ->setPriority($item['priority'] ?? null)
            ->setIsPublished($item['isPublished'] ?? false)
            // Defaults to true, so an export predating this field doesn't silently drop its pages from the sitemap on import
            ->setIsIndexable($item['isIndexable'] ?? true)
            ->setSummarySocialNetwork($item['summarySocialNetwork'] ?? null)
            ->setModification($now);
    }

    // Existing Blocks have no natural key to match the imported ones against, so the whole collection is replaced - BlockRemovalListener removes the orphaned rows (and their Medias) on flush
    private function replaceBlocks(Page $page, array $blocksData, ?string $filesDir): void
    {
        foreach ($page->getBlocks()->toArray() as $existingBlock) {
            $page->removeBlock($existingBlock);
        }

        foreach ($this->blockDataImporter->buildBlocks($blocksData, $filesDir) as $block) {
            $page->addBlock($block);
        }
    }

    // ogImage is exclusively owned by this Page (see Page::$ogImage's cascade), unlike Block medias there's no listener to orphan-remove it on its own - dropped by hand before a replacement (if any) is built
    private function replaceOgImage(Page $page, ?array $ogImageData, ?string $filesDir): void
    {
        $existing = $page->getOgImage();
        if (null !== $existing) {
            $page->setOgImage(null);
            $this->em->remove($existing);
        }

        if (null === $ogImageData) {
            return;
        }

        $ogImage = $this->blockDataImporter->buildMedia($ogImageData, $filesDir);
        $this->em->persist($ogImage);
        $page->setOgImage($ogImage);
    }
}
