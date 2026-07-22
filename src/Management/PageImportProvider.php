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
use c975L\SiteBundle\Service\DefaultPagesImporter;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use Doctrine\ORM\EntityManagerInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// Imports a "site_page" content export (see PageCrudController::exportSelection/ContentExporter) - unlike DefaultPagesImporter::import() (which only ever creates the fixed default pages and skips ones that already exist), this always overwrites: the whole point is pushing a page built in dev on top of whatever exists in prod under the same slug. Matches by slug, never by id (which won't match between environments) - Block has no natural key of its own, so its entire collection is replaced rather than diffed
class PageImportProvider implements ImportProviderInterface
{
    public const KIND = 'site_page';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly PageRepository $pageRepository,
        private readonly DefaultPagesImporter $defaultPagesImporter,
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
                ->setModification($now);

            // Existing Blocks have no natural key to match the imported ones against, so the whole collection is replaced - BlockRemovalListener removes the orphaned rows (and their Medias) on flush
            foreach ($page->getBlocks()->toArray() as $existingBlock) {
                $page->removeBlock($existingBlock);
            }

            foreach ($item['blocks'] ?? [] as $blockData) {
                $this->defaultPagesImporter->ensureFormBlockDependenciesExist($blockData);

                $block = (new Block())
                    ->setKind($blockData['kind'])
                    ->setPosition($blockData['position'])
                    ->setData($blockData['data'] ?? []);
                $this->em->persist($block);
                $page->addBlock($block);

                foreach ($blockData['medias'] ?? [] as $mediaData) {
                    $media = $this->buildMedia($mediaData, $filesDir);
                    $this->em->persist($media);
                    $block->addMedia($media);
                }
            }

            $this->em->persist($page);
            $isNew ? $created++ : $updated++;
        }

        $this->em->flush();

        return ['created' => $created, 'updated' => $updated];
    }

    // Rebuilds a Media from its exported metadata, its file read straight from the extracted zip archive (see ContentImportController) and run through Vich's normal upload pipeline via ReplacingFile (a plain File is silently ignored by Vich's UploadHandler, see PageCrudController::cloneMedia()), so filename/size/mimeType/thumbnails all get regenerated here rather than trusting the exporting environment's values
    private function buildMedia(array $mediaData, ?string $filesDir): Media
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
