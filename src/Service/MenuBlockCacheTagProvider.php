<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\SiteBundle\Twig\MenuExtension;
use c975L\UiBundle\Contract\BlockCacheTagProviderInterface;
use c975L\UiBundle\Entity\Block;

// Lets a "menu_link" be cached like any other block, its active state being set in the browser (see menu-active.js) - the tags, and the few links still rendered live, are decided by MenuExtension, which owns what a target resolves to. A "menu_group" needs nothing of its own, holding its links' tags and vetoes as a container (see BlockCacheTagResolver)
class MenuBlockCacheTagProvider implements BlockCacheTagProviderInterface
{
    public function __construct(private readonly MenuExtension $menuExtension)
    {
    }

    public function getCacheTagResolvers(): array
    {
        return ['menu_link' => $this->resolve(...)];
    }

    /** @return string[]|null */
    private function resolve(Block $block): ?array
    {
        $data = $block->getData();

        return $this->menuExtension->getMenuLinkCacheTags($data['target'] ?? null, $data['label'] ?? null);
    }
}
