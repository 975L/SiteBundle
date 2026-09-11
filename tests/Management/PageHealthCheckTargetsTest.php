<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\PageHealthCheckTargets;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PageEditUrlResolver;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\PageTranslator;
use c975L\SiteBundle\Tests\PagePublicUrlGeneratorTestTrait;
use PHPUnit\Framework\TestCase;

class PageHealthCheckTargetsTest extends TestCase
{
    use PagePublicUrlGeneratorTestTrait;

    // A site declaring one language, which is every c975L site until it says otherwise: one row per page, exactly as before
    public function testASinglePageWrittenInOneLanguageIsOneTarget(): void
    {
        $targets = $this->targets(['fr'])->all();

        $this->assertCount(1, $targets);
        $this->assertSame('https://example.com/pages/nos-ateliers', $targets[0]['url']);
        $this->assertSame('Nos ateliers', $targets[0]['label']);
        $this->assertSame('/management/page/1/edit', $targets[0]['editUrl']);
        $this->assertSame('fr', $targets[0]['locale']);
    }

    // A page read at "/en/pages/..." is another page to a crawler, a validator and a performance report, so it earns a row of its own
    public function testAPageWrittenInTwoLanguagesIsTwoTargets(): void
    {
        $targets = $this->targets(['fr', 'en'])->all();

        $this->assertCount(2, $targets);
        $this->assertSame('https://example.com/en/pages/nos-ateliers', $targets[1]['url']);
        $this->assertSame('en', $targets[1]['locale']);
    }

    // Told apart at a glance on a dashboard listing both, the language named in its own words
    public function testATranslatedRowCarriesTheLanguageInItsLabel(): void
    {
        $this->assertSame('Our workshops (English)', $this->targets(['fr', 'en'])->all()[1]['label']);
    }

    // Named as a label names it and not as a sentence does: Intl holds "español" lowercase, which is how the word reads inside a Spanish sentence and not how it reads alone in a bracket
    public function testTheLanguageIsNamedAsALabelNamesIt(): void
    {
        $labels = array_column($this->targets(['fr', 'en', 'es'])->all(), 'label');

        $this->assertContains('Nos ateliers (Español)', $labels);
    }

    // And its link leads to the screen that language is written on, not to the one the page was written in
    public function testATranslatedRowLeadsToThatLanguagesScreen(): void
    {
        $this->assertStringContainsString('contenu=en', $this->targets(['fr', 'en'])->all()[1]['editUrl']);
    }

    // Thrown rather than returned empty: these kinds are exhaustive, so an empty run would tell HealthCheckRunner every stored row is stale and clear them
    public function testAnUnconfiguredSiteUrlThrowsRatherThanClearingEveryStoredRow(): void
    {
        $this->expectException(\RuntimeException::class);

        $this->targets(['fr'], siteUrl: null)->all();
    }

    // Read ahead once for the whole set: without it, every page costs a query per language, the walk being run over every page of the site (the same reading SitePageSitemapProvider does)
    public function testTheTranslationsAreReadAheadRatherThanPageByPage(): void
    {
        $translator = $this->createMock(PageTranslator::class);
        $translator->expects($this->once())->method('preload');
        // The read-ahead is only worth its query if the labels then come out of it: all() would go straight back to base, page by page and language by language, whatever was read ahead
        $translator->expects($this->never())->method('all');
        $translator->method('translatedLocales')->willReturn(['fr']);

        $this->targets(['fr'], translator: $translator)->all();
    }

    /** @param list<string> $translatedLocales */
    private function targets(array $translatedLocales, ?string $siteUrl = 'https://example.com', ?PageTranslator $translator = null): PageHealthCheckTargets
    {
        $page = new Page()->setTitle('Nos ateliers')->setSlug('nos-ateliers');
        new \ReflectionProperty(Page::class, 'id')->setValue($page, 1);

        $repository = $this->createStub(PageRepository::class);
        $repository->method('findAllOrdered')->willReturn([$page]);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        if (null === $translator) {
            $translator = $this->createStub(PageTranslator::class);
            $translator->method('translatedLocales')->willReturn($translatedLocales);
            $translator->method('value')->willReturnCallback(
                static fn (Page $page, string $locale, string $field): ?string => 'en' === $locale && 'title' === $field ? 'Our workshops' : null
            );
        }

        $editUrlResolver = $this->createStub(PageEditUrlResolver::class);
        $editUrlResolver->method('resolve')->willReturnCallback(
            static fn (Page $page, ?string $locale = null): string => '/management/page/1/edit' . (\in_array($locale, [null, 'fr'], true) ? '' : '?contenu=' . $locale)
        );

        return new PageHealthCheckTargets(
            $repository,
            new PagePublicUrlResolver($configService, $this->createUrlGenerator(), $this->createSiteLocales(), $translator),
            $editUrlResolver,
            $translator,
            $this->createSiteLocales(),
        );
    }
}
