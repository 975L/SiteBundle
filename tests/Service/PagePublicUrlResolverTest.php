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
use c975L\SiteBundle\Service\PageTranslator;
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

    /**
     * @param list<string> $translatedLocales the languages the page itself was written in - the writing one is always among them
     */
    private function createResolver(?string $siteUrl, array $declaredLocales = ['fr'], string $defaultLocale = 'fr', array $translatedLocales = ['fr']): PagePublicUrlResolver
    {
        $pageTranslator = $this->createStub(PageTranslator::class);
        $pageTranslator->method('translatedLocales')->willReturn($translatedLocales);

        return new PagePublicUrlResolver(
            $this->createConfigService($siteUrl),
            $this->createUrlGenerator(),
            $this->createSiteLocales($declaredLocales, $defaultLocale),
            $pageTranslator,
        );
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
        $resolver = $this->createResolver('https://exemple.com', ['fr'], 'fr');

        $this->assertSame([], $resolver->resolveAlternates($this->createPage('contact')));
    }

    // The writing language keeps its original url, the others go through the prefix: this is what leaves an existing site untouched
    public function testEveryLanguageThePageIsWrittenInNamesIt(): void
    {
        $resolver = $this->createResolver('https://exemple.com', ['fr', 'en'], 'fr', ['fr', 'en']);

        $this->assertSame([
            'fr' => 'https://exemple.com/pages/contact',
            'en' => 'https://exemple.com/en/pages/contact',
        ], $resolver->resolveAlternates($this->createPage('contact')));
    }

    // The heart of it: a language the site declares is not a language the page exists in. Declaring the whole list is what had "/en/" serve the French page under lang="en", which a search engine reads as duplicated content
    public function testAPageNobodyTranslatedDeclaresNoGroupEvenOnABilingualSite(): void
    {
        $resolver = $this->createResolver('https://exemple.com', ['fr', 'en'], 'fr', ['fr']);

        $this->assertSame([], $resolver->resolveAlternates($this->createPage('contact')));
    }

    // A group naming languages but not the page it is written on is invalid, the same contract as BookBundle's book_alternates()
    public function testAGroupThatDoesNotNameTheWritingLanguageIsDropped(): void
    {
        $resolver = $this->createResolver('https://exemple.com', ['fr', 'en'], 'fr', ['en']);

        $this->assertSame([], $resolver->resolveAlternates($this->createPage('contact')));
    }

    // The home page has one url per language: the site root, and each language's own root
    public function testTheHomePageNamesTheRootOfEachLanguage(): void
    {
        $resolver = $this->createResolver('https://exemple.com', ['fr', 'en'], 'fr', ['fr', 'en']);

        $this->assertSame([
            'fr' => 'https://exemple.com/',
            'en' => 'https://exemple.com/en/',
        ], $resolver->resolveAlternates($this->createPage('home')));
    }

    public function testResolveReturnsNullWithoutASiteUrl(): void
    {
        $this->assertNull($this->createResolver(null)->resolve($this->createPage('contact')));
    }

    public function testResolveBuildsTheSiteRootForHome(): void
    {
        $this->assertSame('https://example.com/', $this->createResolver('https://example.com')->resolve($this->createPage('home')));
    }

    // No trailing slash - both forms answer 200, and this is the canonical one, shared with the sitemap (see SitePageSitemapProvider)
    public function testResolveBuildsARegularPageUrlWithoutTrailingSlash(): void
    {
        $this->assertSame('https://example.com/pages/contact', $this->createResolver('https://example.com')->resolve($this->createPage('contact')));
    }

    public function testResolvePathBuildsTheSiteRootForHome(): void
    {
        $this->assertSame('/', $this->createResolver('https://example.com')->resolvePath($this->createPage('home')));
    }

    public function testResolvePathBuildsARegularPagePathWithoutTrailingSlash(): void
    {
        $this->assertSame('/pages/contact', $this->createResolver('https://example.com')->resolvePath($this->createPage('contact')));
    }

    // The local path has no host to build, so it stays available where resolve() returns null (see PageDevProfilePathProvider)
    public function testResolvePathWorksWithoutASiteUrl(): void
    {
        $this->assertSame('/pages/contact', $this->createResolver(null)->resolvePath($this->createPage('contact')));
    }

    // A site declaring its languages without naming the one it is written in is still bilingual: the writing language is part of the group whether or not the config repeats it (see SiteLocales::all())
    public function testTheWritingLanguageIsInTheGroupEvenWhenTheConfigDoesNotNameIt(): void
    {
        $resolver = $this->createResolver('https://exemple.com', ['en'], 'fr', ['fr', 'en']);

        $this->assertSame([
            'fr' => 'https://exemple.com/pages/contact',
            'en' => 'https://exemple.com/en/pages/contact',
        ], $resolver->resolveAlternates($this->createPage('contact')));
    }
}
