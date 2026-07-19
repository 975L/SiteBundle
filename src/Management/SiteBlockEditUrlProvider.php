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
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Contract\BlockEditUrlProviderInterface;
use c975L\UiBundle\Entity\Block;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// Resolves, for UiBundle's front-end "Edit this block" hover button, the EasyAdmin edit URL of the Page owning a given Block
class SiteBlockEditUrlProvider implements BlockEditUrlProviderInterface
{
    use BlockFocusUrlTrait;

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
    ) {
    }

    public function getEditUrls(array $blocks): array
    {
        $blockIds = array_filter(array_map(static fn (Block $block): ?int => $block->getId(), $blocks));

        if ([] === $blockIds) {
            return [];
        }

        $urls = [];
        foreach ($this->pageRepository->findByBlockIds($blockIds) as $page) {
            foreach ($page->getBlocks() as $block) {
                if (\in_array($block->getId(), $blockIds, true)) {
                    $urls[$block->getId()] = $this->blockFocusUrl($this->adminUrlGenerator, PageCrudController::class, $page->getId(), $block);
                }
            }
        }

        return $urls;
    }
}
