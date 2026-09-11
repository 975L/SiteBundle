<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\PageBlockLocator;
use c975L\SiteBundle\Management\PageContentOffenceLocator;
use c975L\SiteBundle\Management\PageLocaleSource;
use c975L\SiteBundle\Service\PageTranslator;
use PHPUnit\Framework\TestCase;

class PageContentOffenceLocatorTest extends TestCase
{
    // The locator only decides whether the source is a page of this bundle and which language it was read in, the tracing itself being PageBlockLocator's - so the double just echoes back what it was asked about, the language included
    private function createLocator(): PageContentOffenceLocator
    {
        $pageBlockLocator = $this->createStub(PageBlockLocator::class);
        $pageBlockLocator->method('locateImage')->willReturnCallback(
            static fn (Page $page, string $src, ?string $contentLocale = null): array => ['editUrl' => '/management?focusBlock=12' . ($contentLocale ?? ''), 'label' => 'Image ' . $src]
        );
        $pageBlockLocator->method('locateLink')->willReturnCallback(
            static fn (Page $page, string $href, ?string $contentLocale = null): array => ['editUrl' => '/management?focusBlock=13' . ($contentLocale ?? ''), 'label' => 'Link ' . $href]
        );

        $pageTranslator = $this->createStub(PageTranslator::class);
        $pageTranslator->method('getTranslatableLocales')->willReturn(['en', 'es']);

        return new PageContentOffenceLocator($pageBlockLocator, $pageTranslator);
    }

    public function testSupportsAPageReadInALanguage(): void
    {
        $this->assertTrue($this->createLocator()->supports(new PageLocaleSource(new Page(), 'fr')));
    }

    public function testDoesNotSupportAnotherSource(): void
    {
        $this->assertFalse($this->createLocator()->supports(new \stdClass()));
    }

    public function testLocateImageDelegatesToThePageBlockLocator(): void
    {
        $located = $this->createLocator()->locateImage(new PageLocaleSource(new Page(), 'fr'), 'https://example.com/images/beach-holiday.jpg');

        $this->assertSame('/management?focusBlock=12', $located['editUrl']);
        $this->assertSame('Image https://example.com/images/beach-holiday.jpg', $located['label']);
    }

    public function testLocateLinkDelegatesToThePageBlockLocator(): void
    {
        $located = $this->createLocator()->locateLink(new PageLocaleSource(new Page(), 'fr'), 'https://example.com/pages/contact');

        $this->assertSame('/management?focusBlock=13', $located['editUrl']);
        $this->assertSame('Link https://example.com/pages/contact', $located['label']);
    }

    // An offence read on the English url is corrected on the English screen: the row's own edit link opens it, and the link to the block has to open the same one
    public function testAnOffenceReadInAnotherLanguageIsTracedToThatLanguagesScreen(): void
    {
        $locator = $this->createLocator();
        $source = new PageLocaleSource(new Page(), 'en');

        $this->assertSame('/management?focusBlock=12en', $locator->locateImage($source, 'https://example.com/images/beach-holiday.jpg')['editUrl']);
        $this->assertSame('/management?focusBlock=13en', $locator->locateLink($source, 'https://example.com/pages/contact')['editUrl']);
    }

    // ContentQualityAnalyzer walks every source it was handed, so a locator asked about one it doesn't own answers null rather than tracing it
    public function testLocatingOnAnotherSourceReturnsNull(): void
    {
        $locator = $this->createLocator();

        $this->assertNull($locator->locateImage(new \stdClass(), 'https://example.com/images/beach-holiday.jpg'));
        $this->assertNull($locator->locateLink(new \stdClass(), 'https://example.com/pages/contact'));
    }
}
