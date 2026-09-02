<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\SiteBundle\Controller\Management\PageCrudController;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Contract\MediaUsageProviderInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Service\BlockFocusUrl;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Resolves, for UiBundle's generic Media library, where a Media is used within SiteBundle's own entities: as a Page's og-image, or attached to a Block owned by a Page. The site-wide graphic roles (favicon, logo...) are UiBundle's own, see its SiteGraphicMediaUsageProvider
class SiteMediaUsageProvider implements MediaUsageProviderInterface
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getUsages(array $medias): array
    {
        $usages = [];
        $this->addBlockUsages($usages, $medias);
        $this->addOgImageUsages($usages, array_map(static fn (Media $media): ?int => $media->getId(), $medias));

        return $usages;
    }

    // Every page holding a block one of these medias belongs to, in a single query for the whole batch rather than one per media
    private function addBlockUsages(array &$usages, array $medias): void
    {
        $blockIdToMediaIds = [];
        foreach ($medias as $media) {
            if (null !== $block = $media->getBlock()) {
                $blockIdToMediaIds[$block->getId()][] = $media->getId();
            }
        }

        if ([] === $blockIdToMediaIds) {
            return;
        }

        foreach ($this->pageRepository->findByBlockIds(array_keys($blockIdToMediaIds)) as $page) {
            foreach ($page->getBlocks() as $block) {
                foreach ($blockIdToMediaIds[$block->getId()] ?? [] as $mediaId) {
                    $usages[$mediaId][] = [
                        'label' => $this->translator->trans('label.media_used_in_page_block', [
                            '%block%' => (string) $block,
                            '%page%' => $page->getTitle(),
                        ], 'site'),
                        'url' => $this->pageEditUrl($page, $block),
                        // A page in the bin still holds its blocks, and its medias with them: the usage is reported as it stands, only marked for what it is (see MediaUsageProviderInterface)
                        'binned' => $page->isDeleted(),
                    ];
                }
            }
        }
    }

    // A page's share image belongs to the page itself, not to any of its blocks
    private function addOgImageUsages(array &$usages, array $mediaIds): void
    {
        $pagesWithOgImage = $this->pageRepository->createQueryBuilder('p')
            ->andWhere('IDENTITY(p.ogImage) IN (:mediaIds)')
            ->setParameter('mediaIds', $mediaIds)
            ->getQuery()
            ->getResult()
        ;

        foreach ($pagesWithOgImage as $page) {
            $usages[$page->getOgImage()->getId()][] = [
                'label' => $this->translator->trans('label.media_used_as_og_image', ['%page%' => $page->getTitle()], 'site'),
                'url' => $this->pageEditUrl($page),
                'binned' => $page->isDeleted(),
            ];
        }
    }

    // $block: when given, the URL also opens/scrolls straight to that block's row on the Page edit form (see BlockFocusController) instead of leaving the user to find it among every other block
    private function pageEditUrl(Page $page, ?Block $block = null): string
    {
        return BlockFocusUrl::build($this->adminUrlGenerator, PageCrudController::class, $page->getId(), $block);
    }
}
