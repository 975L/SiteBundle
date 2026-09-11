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
use c975L\SiteBundle\Service\PageTranslator;

// Hands ContentQualityAnalyzer (ConfigBundle) the block behind an image or a link it found on a page, so the Health check panel links straight to what has to be fixed. All the tracing itself is PageBlockLocator's - this only says "that source is a page of mine", which is the one thing ConfigBundle can't know, and in which language it was read
class PageContentOffenceLocator implements ContentOffenceLocatorInterface
{
    public function __construct(
        private readonly PageBlockLocator $pageBlockLocator,
        private readonly PageTranslator $pageTranslator,
    ) {
    }

    public function supports(object $source): bool
    {
        return $source instanceof PageLocaleSource;
    }

    public function locateImage(object $source, string $src): ?array
    {
        return $source instanceof PageLocaleSource
            ? $this->pageBlockLocator->locateImage($source->page, $src, $this->contentLocale($source))
            : null;
    }

    public function locateLink(object $source, string $href): ?array
    {
        return $source instanceof PageLocaleSource
            ? $this->pageBlockLocator->locateLink($source->page, $href, $this->contentLocale($source))
            : null;
    }

    // The language screen to open, or none where the row is the one the site is written in - which is edited on the screen it always was (the same reading SiteBlockEditUrlProvider does)
    private function contentLocale(PageLocaleSource $source): ?string
    {
        return \in_array($source->locale, $this->pageTranslator->getTranslatableLocales(), true) ? $source->locale : null;
    }
}
