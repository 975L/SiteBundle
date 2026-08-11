<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\SiteBundle\Controller\Management\MenuCrudController;
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\UiBundle\Contract\BlockEditUrlProviderInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Service\BlockFocusUrl;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// Resolves, for UiBundle's front-end "Edit this block" hover button, the EasyAdmin edit URL of the footer Menu owning a given Block - what SiteBlockEditUrlProvider does for the blocks a Page owns, and which left a footer's own items with no button at all
class MenuBlockEditUrlProvider implements BlockEditUrlProviderInterface
{
    public function __construct(
        private readonly MenuRepository $menuRepository,
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
        foreach ($this->menuRepository->findByBlockIds($blockIds) as $menu) {
            // The navbar renders the very same blocks (see Navbar.html.twig) and is deliberately left out: it is hovered on every single visit just to navigate, and a floating button popping over each of its links would be in the way rather than of help. The footer is the menu an editor actually reaches for
            if (Menu::LOCATION_FOOTER !== $menu->getLocation()) {
                continue;
            }

            foreach ($menu->getBlocks() as $block) {
                if (\in_array($block->getId(), $blockIds, true)) {
                    $urls[$block->getId()] = BlockFocusUrl::build($this->adminUrlGenerator, MenuCrudController::class, $menu->getId(), $block);
                }
            }
        }

        return $urls;
    }
}
