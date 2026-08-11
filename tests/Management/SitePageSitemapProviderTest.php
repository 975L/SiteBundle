<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\ConfigBundle\Management\SelfCheckedSitemapProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\SitePageSitemapProvider;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\PageServiceInterface;
use c975L\SiteBundle\Tests\PagePublicUrlGeneratorTestTrait;
use PHPUnit\Framework\TestCase;

class SitePageSitemapProviderTest extends TestCase
{
    use PagePublicUrlGeneratorTestTrait;

    private function createConfigService(string $urlRoot = 'https://example.com'): ConfigServiceInterface
    {
        $service = $this->createStub(ConfigServiceInterface::class);
        $service->method('get')->willReturn($urlRoot);

        return $service;
    }

    private function createPageService(array $pages): PageServiceInterface
    {
        $service = $this->createStub(PageServiceInterface::class);
        $service->method('findAll')->willReturn($pages);

        return $service;
    }

    // A real PagePublicUrlResolver over a real UrlGenerator, so the urls asserted below are the ones the routes actually produce
    private function createProvider(array $pages = [], string $urlRoot = 'https://example.com'): SitePageSitemapProvider
    {
        return new SitePageSitemapProvider(
            new PagePublicUrlResolver($this->createConfigService($urlRoot), $this->createUrlGenerator()),
            $this->createPageService($pages)
        );
    }

    // The name is what the written file is called, public/sitemap-site.xml - checked by SeoFilesHealthCheckProvider and declared in the index
    public function testSitemapNameIsSite(): void
    {
        $this->assertSame('site', $this->createProvider()->getSitemapName());
    }

    // Self-checked, so DeclaredUrlsHealthCheckPass builds no generic declared-urls check for this sitemap - ContentQualityHealthCheckProvider already reports these very pages, in more detail
    public function testProviderIsSelfChecked(): void
    {
        $this->assertInstanceOf(SelfCheckedSitemapProviderInterface::class, $this->createProvider());
    }

    // Each page is turned into an absolute URL, using its own priority/changeFrequency
    public function testGetUrlsBuildsAbsoluteUrlsFromPageAttributes(): void
    {
        $page = new Page()->setTitle('About')->setSlug('about')->setPriority(7)->setChangeFrequency('daily');
        $page->setModification(new \DateTime('2026-01-15'));
        $provider = $this->createProvider([$page], 'https://example.com');

        $urls = $provider->getUrls();

        $this->assertSame([
            'loc' => 'https://example.com/pages/about',
            'lastmod' => '2026-01-15',
            'changefreq' => 'daily',
            'priority' => 7,
            'title' => 'About',
            'description' => null,
        ], $urls[0]);
    }

    // Ignored by the sitemap, these two are what ConfigBundle's SeoFilesWriter lists the pages in llms.txt from - the social network summary doubling as the page's meta description
    public function testGetUrlsCarriesTheTitleAndSummaryForLlmsTxt(): void
    {
        $page = new Page()->setTitle('About')->setSlug('about')->setSummarySocialNetwork('Who we are');
        $page->setModification(new \DateTime('2026-01-15'));

        $urls = $this->createProvider([$page])->getUrls();

        $this->assertSame('About', $urls[0]['title']);
        $this->assertSame('Who we are', $urls[0]['description']);
    }

    // A page with no explicit priority/changeFrequency falls back to sensible sitemap defaults
    public function testGetUrlsFallsBackToDefaultPriorityAndChangeFrequency(): void
    {
        $page = new Page()->setTitle('Home')->setSlug('home');
        $page->setModification(new \DateTime('2026-01-15'));
        $provider = $this->createProvider([$page]);

        $urls = $provider->getUrls();

        $this->assertSame('weekly', $urls[0]['changefreq']);
        $this->assertSame(4, $urls[0]['priority']);
    }

    // A priority of 0 stays 0, not the default - an admin explicitly deprioritizing a page is not the same as never setting one
    public function testGetUrlsKeepsAnExplicitZeroPriority(): void
    {
        $page = new Page()->setTitle('About')->setSlug('about')->setPriority(0);
        $page->setModification(new \DateTime('2026-01-15'));

        $this->assertSame(0, $this->createProvider([$page])->getUrls()[0]['priority']);
    }

    // The home page is declared at the site root, never as "/pages/home" - that form only 301s to the root, and a sitemap must not list redirects
    public function testGetUrlsDeclaresTheHomePageAtTheSiteRoot(): void
    {
        $page = new Page()->setTitle('Home')->setSlug('home');
        $page->setModification(new \DateTime('2026-01-15'));
        $provider = $this->createProvider([$page]);

        $this->assertSame('https://example.com/', $provider->getUrls()[0]['loc']);
    }

    // A page marked as non-indexable is left out entirely, the indexable ones around it aren't affected
    public function testGetUrlsSkipsNonIndexablePages(): void
    {
        $indexable = new Page()->setTitle('About')->setSlug('about');
        $indexable->setModification(new \DateTime('2026-01-15'));
        $excluded = new Page()->setTitle('Créer un compte')->setSlug('creer-un-compte')->setIsIndexable(false);
        $excluded->setModification(new \DateTime('2026-01-15'));
        $provider = $this->createProvider([$indexable, $excluded]);

        $urls = $provider->getUrls();

        $this->assertCount(1, $urls);
        $this->assertSame('https://example.com/pages/about', $urls[0]['loc']);
    }

    // Pages are indexable unless explicitly opted out, so nothing is silently dropped from an existing sitemap
    public function testPagesAreIndexableByDefault(): void
    {
        $this->assertTrue(new Page()->isIndexable());
    }

    public function testGetUrlsReturnsEmptyArrayWhenNoPages(): void
    {
        $this->assertSame([], $this->createProvider([])->getUrls());
    }

    // Without "site-url" configured, PagePublicUrlResolver can't build absolute urls, and a sitemap accepts nothing else
    public function testGetUrlsReturnsEmptyArrayWhenSiteUrlIsNotConfigured(): void
    {
        $page = new Page()->setTitle('About')->setSlug('about');
        $page->setModification(new \DateTime('2026-01-15'));

        $this->assertSame([], $this->createProvider([$page], '')->getUrls());
    }
}
