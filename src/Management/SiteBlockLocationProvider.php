<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\UiBundle\Contract\BlockLocationProviderInterface;
use c975L\UiBundle\Entity\Block;

// Tells UiBundle's site-wide block screens (the "Legal models" list and its drift health check) which Page a given Block sits on, and at which public address - the counterpart of SiteBlockEditUrlProvider, which answers where it is edited rather than where it is read. Nothing here is legal-specific: any screen listing one kind across the whole site gets its locations from this
class SiteBlockLocationProvider implements BlockLocationProviderInterface
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PagePublicUrlResolver $pagePublicUrlResolver,
    ) {
    }

    public function getLocations(array $blocks): array
    {
        $blockIds = array_filter(array_map(static fn (Block $block): ?int => $block->getId(), $blocks));

        if ([] === $blockIds) {
            return [];
        }

        $locations = [];
        foreach ($this->pageRepository->findByBlockIds($blockIds) as $page) {
            foreach ($page->getBlocks() as $block) {
                if (!\in_array($block->getId(), $blockIds, true)) {
                    continue;
                }

                $locations[$block->getId()] = [
                    'label' => (string) $page->getTitle(),
                    // Null when the page is in the trash or unpublished, or when "site-url" isn't configured yet: there is no address to send a visitor - nor a health check - to
                    'url' => $page->isPublished() && !$page->isDeleted()
                        ? $this->pagePublicUrlResolver->resolve($page)
                        : null,
                    'published' => $page->isPublished() && !$page->isDeleted(),
                ];
            }
        }

        return $locations;
    }
}
