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
use c975L\SiteBundle\Controller\Management\SiteGraphicCrudController;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Contract\MediaUsageProviderInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Resolves, for UiBundle's generic Media library, where a Media is used within SiteBundle's own
// entities: as a site-wide graphic role, as a Page's og-image, or attached to a Block owned by a Page
class SiteMediaUsageProvider implements MediaUsageProviderInterface
{
    private const ROLE_LABELS = [
        Media::ROLE_FAVICON => 'label.favicon',
        Media::ROLE_APPLE_TOUCH_ICON => 'label.apple_touch_icon',
        Media::ROLE_OG_IMAGE => 'label.og_image',
        Media::ROLE_LOGO => 'label.logo',
        Media::ROLE_ERROR_IMAGE => 'label.error_image',
    ];

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getUsages(array $medias): array
    {
        $usages = [];
        $mediaIds = array_map(static fn (Media $media): ?int => $media->getId(), $medias);

        foreach ($medias as $media) {
            if (null === $media->getRole()) {
                continue;
            }

            $usages[$media->getId()][] = [
                'label' => $this->translator->trans(self::ROLE_LABELS[$media->getRole()] ?? $media->getRole(), [], 'site'),
                'url' => $this->adminUrlGenerator
                    ->unsetAll()
                    ->setController(SiteGraphicCrudController::class)
                    ->setAction(Action::EDIT)
                    ->setEntityId($media->getId())
                    ->generateUrl(),
            ];
        }

        $blockIdToMediaIds = [];
        foreach ($medias as $media) {
            if (null !== $block = $media->getBlock()) {
                $blockIdToMediaIds[$block->getId()][] = $media->getId();
            }
        }

        if ([] !== $blockIdToMediaIds) {
            foreach ($this->pageRepository->findByBlockIds(array_keys($blockIdToMediaIds)) as $page) {
                foreach ($page->getBlocks() as $block) {
                    foreach ($blockIdToMediaIds[$block->getId()] ?? [] as $mediaId) {
                        $usages[$mediaId][] = [
                            'label' => $this->translator->trans('label.media_used_in_page_block', [
                                '%block%' => (string) $block,
                                '%page%' => $page->getTitle(),
                            ], 'site'),
                            'url' => $this->pageEditUrl($page, $block),
                        ];
                    }
                }
            }
        }

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
            ];
        }

        return $usages;
    }

    // $block: when given, the URL also opens/scrolls straight to that block's row on the Page edit
    // form (see BlockFocusController) instead of leaving the user to find it among every other block
    private function pageEditUrl(Page $page, ?Block $block = null): string
    {
        $urlGenerator = $this->adminUrlGenerator
            ->unsetAll()
            ->setController(PageCrudController::class)
            ->setAction(Action::EDIT)
            ->setEntityId($page->getId());

        if (null !== $block) {
            $urlGenerator->set('focusBlock', $block->getId());
        }

        return $urlGenerator->generateUrl();
    }
}
