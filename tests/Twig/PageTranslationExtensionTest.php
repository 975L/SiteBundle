<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Twig;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\PageTranslator;
use c975L\SiteBundle\Twig\PageTranslationExtension;
use PHPUnit\Framework\TestCase;

class PageTranslationExtensionTest extends TestCase
{
    private function createPage(): Page
    {
        return new Page()->setSlug('ateliers')->setTitle('Nos ateliers')->setSummarySocialNetwork('Un atelier par mois');
    }

    /**
     * @param array<string, string> $alternates
     */
    private function createExtension(string $title = 'Nos ateliers', ?string $summary = 'Un atelier par mois', array $alternates = []): PageTranslationExtension
    {
        $pageTranslator = $this->createStub(PageTranslator::class);
        $pageTranslator->method('getTitle')->willReturn($title);
        $pageTranslator->method('getSummarySocialNetwork')->willReturn($summary);

        $pagePublicUrlResolver = $this->createStub(PagePublicUrlResolver::class);
        $pagePublicUrlResolver->method('resolveAlternates')->willReturn($alternates);

        return new PageTranslationExtension($pageTranslator, $pagePublicUrlResolver);
    }

    // The title a template reads is the one of the language being served, never the raw column
    public function testTheTitleIsTheTranslatedOne(): void
    {
        $this->assertSame('Our workshops', $this->createExtension('Our workshops')->getTitle($this->createPage()));
    }

    // A site declaring a single language reads back exactly what the page holds
    public function testTheTitleIsThePagesOwnWhenNothingIsTranslated(): void
    {
        $this->assertSame('Nos ateliers', $this->createExtension()->getTitle($this->createPage()));
    }

    public function testTheSummaryIsTheTranslatedOne(): void
    {
        $this->assertSame('One a month', $this->createExtension(summary: 'One a month')->getSummary($this->createPage()));
    }

    // A page carrying no summary at all says nothing rather than an empty string, so the layout falls back on its own sources
    public function testTheSummaryIsNullWhenThePageCarriesNone(): void
    {
        $this->assertNull($this->createExtension(summary: null)->getSummary($this->createPage()));
    }

    // What the layout writes its "hreflang" links from, the page naming itself in every declared language
    public function testTheAlternatesAreTheResolversOwn(): void
    {
        $alternates = ['fr' => 'https://exemple.com/pages/ateliers', 'en' => 'https://exemple.com/en/pages/ateliers'];

        $this->assertSame($alternates, $this->createExtension(alternates: $alternates)->getAlternates($this->createPage()));
    }

    // Nothing is emitted on a site declaring a single language: the layout skips the tags altogether
    public function testThereAreNoAlternatesOnASiteDeclaringOneLanguage(): void
    {
        $this->assertSame([], $this->createExtension()->getAlternates($this->createPage()));
    }
}
