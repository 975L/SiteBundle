<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Tests\PagePublicUrlGeneratorTestTrait;
use PHPUnit\Framework\TestCase;

class PagePublicUrlResolverTest extends TestCase
{
    use PagePublicUrlGeneratorTestTrait;

    private function createConfigService(?string $siteUrl): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        return $configService;
    }

    private function createPage(string $slug): Page
    {
        $page = new Page();
        $page->setSlug($slug);
        $page->setTitle($slug);

        return $page;
    }

    // A site declaring a single language declares no group: no "hreflang" in the page, no "xhtml:link" in the sitemap
    public function testNoAlternatesOnASiteDeclaringOneLanguage(): void
    {
        $resolver = new PagePublicUrlResolver($this->createConfigService('https://exemple.com'), $this->createUrlGenerator(), $this->createSiteLocales(['fr'], 'fr'));

        $this->assertSame([], $resolver->resolveAlternates($this->createPage('contact')));
    }

    // The writing language keeps its original url, the others go through the prefix: this is what leaves an existing site untouched
    public function testEveryDeclaredLanguageNamesTheSamePage(): void
    {
        $resolver = new PagePublicUrlResolver($this->createConfigService('https://exemple.com'), $this->createUrlGenerator(), $this->createSiteLocales(['fr', 'en'], 'fr'));

        $this->assertSame([
            'fr' => 'https://exemple.com/pages/contact',
            'en' => 'https://exemple.com/en/pages/contact',
        ], $resolver->resolveAlternates($this->createPage('contact')));
    }

    // The home page has one url per language: the site root, and each language's own root
    public function testTheHomePageNamesTheRootOfEachLanguage(): void
    {
        $resolver = new PagePublicUrlResolver($this->createConfigService('https://exemple.com'), $this->createUrlGenerator(), $this->createSiteLocales(['fr', 'en'], 'fr'));

        $this->assertSame([
            'fr' => 'https://exemple.com/',
            'en' => 'https://exemple.com/en/',
        ], $resolver->resolveAlternates($this->createPage('home')));
    }

    public function testResolveReturnsNullWithoutASiteUrl(): void
    {
        $resolver = new PagePublicUrlResolver($this->createConfigService(null), $this->createUrlGenerator(), $this->createSiteLocales());

        $this->assertNull($resolver->resolve($this->createPage('contact')));
    }

    public function testResolveBuildsTheSiteRootForHome(): void
    {
        $resolver = new PagePublicUrlResolver($this->createConfigService('https://example.com'), $this->createUrlGenerator(), $this->createSiteLocales());

        $this->assertSame('https://example.com/', $resolver->resolve($this->createPage('home')));
    }

    // No trailing slash - both forms answer 200, and this is the canonical one, shared with the sitemap (see SitePageSitemapProvider)
    public function testResolveBuildsARegularPageUrlWithoutTrailingSlash(): void
    {
        $resolver = new PagePublicUrlResolver($this->createConfigService('https://example.com'), $this->createUrlGenerator(), $this->createSiteLocales());

        $this->assertSame('https://example.com/pages/contact', $resolver->resolve($this->createPage('contact')));
    }

    public function testResolvePathBuildsTheSiteRootForHome(): void
    {
        $resolver = new PagePublicUrlResolver($this->createConfigService('https://example.com'), $this->createUrlGenerator(), $this->createSiteLocales());

        $this->assertSame('/', $resolver->resolvePath($this->createPage('home')));
    }

    public function testResolvePathBuildsARegularPagePathWithoutTrailingSlash(): void
    {
        $resolver = new PagePublicUrlResolver($this->createConfigService('https://example.com'), $this->createUrlGenerator(), $this->createSiteLocales());

        $this->assertSame('/pages/contact', $resolver->resolvePath($this->createPage('contact')));
    }

    // The local path has no host to build, so it stays available where resolve() returns null (see PageDevProfilePathProvider)
    public function testResolvePathWorksWithoutASiteUrl(): void
    {
        $resolver = new PagePublicUrlResolver($this->createConfigService(null), $this->createUrlGenerator(), $this->createSiteLocales());

        $this->assertSame('/pages/contact', $resolver->resolvePath($this->createPage('contact')));
    }

    // A site declaring its languages without naming the one it is written in is still bilingual: the writing language is part of the group whether or not the config repeats it (see SiteLocales::all())
    public function testTheWritingLanguageIsInTheGroupEvenWhenTheConfigDoesNotNameIt(): void
    {
        $resolver = new PagePublicUrlResolver($this->createConfigService('https://exemple.com'), $this->createUrlGenerator(), $this->createSiteLocales(['en'], 'fr'));

        $this->assertSame([
            'fr' => 'https://exemple.com/pages/contact',
            'en' => 'https://exemple.com/en/pages/contact',
        ], $resolver->resolveAlternates($this->createPage('contact')));
    }

    // A collection item has no Page of its own: its group is built from its own url, the parent Page's group naming somebody else's pages
    public function testACollectionItemNamesItsOwnUrlInEachLanguage(): void
    {
        $resolver = new PagePublicUrlResolver($this->createConfigService('https://exemple.com'), $this->createUrlGenerator(), $this->createSiteLocales(['fr', 'en'], 'fr'));

        $this->assertSame([
            'fr' => 'https://exemple.com/pages/blog/mon-article',
            'en' => 'https://exemple.com/en/pages/blog/mon-article',
        ], $resolver->resolveAlternatesForSlug('blog/mon-article'));
    }

    public function testACollectionItemDeclaresNoGroupOnASiteDeclaringOneLanguage(): void
    {
        $resolver = new PagePublicUrlResolver($this->createConfigService('https://exemple.com'), $this->createUrlGenerator(), $this->createSiteLocales(['fr'], 'fr'));

        $this->assertSame([], $resolver->resolveAlternatesForSlug('blog/mon-article'));
    }
}
