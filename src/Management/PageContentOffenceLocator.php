<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\ContentOffenceLocatorInterface;
use c975L\SiteBundle\Entity\Page;

// Hands ContentQualityAnalyzer (ConfigBundle) the block behind an image or a link it found on a Page, so the Health check panel links straight to what has to be fixed. All the tracing itself is PageBlockLocator's - this only says "that source is a Page of mine", which is the one thing ConfigBundle can't know
class PageContentOffenceLocator implements ContentOffenceLocatorInterface
{
    public function __construct(
        private readonly PageBlockLocator $pageBlockLocator,
    ) {
    }

    public function supports(object $source): bool
    {
        return $source instanceof Page;
    }

    public function locateImage(object $source, string $src): ?array
    {
        return $source instanceof Page ? $this->pageBlockLocator->locateImage($source, $src) : null;
    }

    public function locateLink(object $source, string $href): ?array
    {
        return $source instanceof Page ? $this->pageBlockLocator->locateLink($source, $href) : null;
    }
}
