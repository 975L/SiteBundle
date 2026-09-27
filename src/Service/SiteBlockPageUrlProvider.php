<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Contract\BlockPageUrlProviderInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Answers UiBundle's "block_page_url" with the published Page carrying a block of that kind, pointed at the block itself when it has an anchor
class SiteBlockPageUrlProvider implements BlockPageUrlProviderInterface
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    // The page's url, plus the block's anchor as BlockExtension renders it ("<anchor>-<id>")
    public function getBlockPageUrl(string $kind): ?string
    {
        $page = $this->pageRepository->findOneByBlockKind($kind);
        if (null === $page) {
            return null;
        }

        $url = $this->urlGenerator->generate('page_display', ['page' => $page->getSlug()]);
        foreach ($page->getBlocks() as $block) {
            $anchor = $block->getData()['anchor'] ?? null;
            if ($kind === $block->getKind() && $anchor) {
                return $url . '#' . $anchor . '-' . $block->getId();
            }
        }

        return $url;
    }
}
