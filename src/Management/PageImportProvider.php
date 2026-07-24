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
            $page = $this->pageRepository->findOneBy(['slug' => $item['slug']]);
            $isNew = null === $page;
            $page ??= (new Page())->setCreation($now);

            $page
                ->setTitle($item['title'])
                ->setSlug($item['slug'])
                ->setChangeFrequency($item['changeFrequency'] ?? null)
                ->setPriority($item['priority'] ?? null)
                ->setIsPublished($item['isPublished'] ?? false)
                ->setSummarySocialNetwork($item['summarySocialNetwork'] ?? null)
                ->setModification($now);

            // Existing Blocks have no natural key to match the imported ones against, so the whole collection is replaced - BlockRemovalListener removes the orphaned rows (and their Medias) on flush
            foreach ($page->getBlocks()->toArray() as $existingBlock) {
                $page->removeBlock($existingBlock);
            }

            foreach ($this->blockDataImporter->buildBlocks($item['blocks'] ?? [], $filesDir) as $block) {
                $page->addBlock($block);
            }

            // ogImage is exclusively owned by this Page (see Page::$ogImage's cascade), unlike Block medias there's no listener to orphan-remove it on its own - dropped by hand before a replacement (if any) is built
            $existingOgImage = $page->getOgImage();
            if (null !== $existingOgImage) {
                $page->setOgImage(null);
                $this->em->remove($existingOgImage);
            }
            if (isset($item['ogImage'])) {
                $ogImage = $this->blockDataImporter->buildMedia($item['ogImage'], $filesDir);
                $this->em->persist($ogImage);
                $page->setOgImage($ogImage);
            }

            $this->em->persist($page);
            $isNew ? $created++ : $updated++;
        }

        $this->em->flush();

        return ['created' => $created, 'updated' => $updated];
    }
}
