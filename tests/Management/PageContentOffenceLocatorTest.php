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
use PHPUnit\Framework\TestCase;

class PageContentOffenceLocatorTest extends TestCase
{
    // The locator only decides whether the source is a Page of this bundle, the tracing itself being PageBlockLocator's - so the double just echoes back what it was asked about
    private function createLocator(): PageContentOffenceLocator
    {
        $pageBlockLocator = $this->createStub(PageBlockLocator::class);
        $pageBlockLocator->method('locateImage')->willReturnCallback(
            static fn (Page $page, string $src): array => ['editUrl' => '/management?focusBlock=12', 'label' => 'Image ' . $src]
        );
        $pageBlockLocator->method('locateLink')->willReturnCallback(
            static fn (Page $page, string $href): array => ['editUrl' => '/management?focusBlock=13', 'label' => 'Link ' . $href]
        );

        return new PageContentOffenceLocator($pageBlockLocator);
    }

    public function testSupportsAPage(): void
    {
        $this->assertTrue($this->createLocator()->supports(new Page()));
    }

    public function testDoesNotSupportAnotherSource(): void
    {
        $this->assertFalse($this->createLocator()->supports(new \stdClass()));
    }

    public function testLocateImageDelegatesToThePageBlockLocator(): void
    {
        $located = $this->createLocator()->locateImage(new Page(), 'https://example.com/images/beach-holiday.jpg');

        $this->assertSame('/management?focusBlock=12', $located['editUrl']);
        $this->assertSame('Image https://example.com/images/beach-holiday.jpg', $located['label']);
    }

    public function testLocateLinkDelegatesToThePageBlockLocator(): void
    {
        $located = $this->createLocator()->locateLink(new Page(), 'https://example.com/pages/contact');

        $this->assertSame('/management?focusBlock=13', $located['editUrl']);
        $this->assertSame('Link https://example.com/pages/contact', $located['label']);
    }

    // ContentQualityAnalyzer walks every source it was handed, so a locator asked about one it doesn't own answers null rather than tracing it
    public function testLocatingOnAnotherSourceReturnsNull(): void
    {
        $locator = $this->createLocator();

        $this->assertNull($locator->locateImage(new \stdClass(), 'https://example.com/images/beach-holiday.jpg'));
        $this->assertNull($locator->locateLink(new \stdClass(), 'https://example.com/pages/contact'));
    }
}
