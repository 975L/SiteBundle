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
use c975L\SiteBundle\Management\PageDevProfilePathProvider;
use c975L\SiteBundle\Management\PageHealthCheckTargets;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PageEditUrlResolver;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Tests\PagePublicUrlGeneratorTestTrait;
use PHPUnit\Framework\TestCase;

class PageDevProfilePathProviderTest extends TestCase
{
    use PagePublicUrlGeneratorTestTrait;

    private function createPage(string $slug, string $title): Page
    {
        $page = new Page();
        $page->setSlug($slug);
        $page->setTitle($title);

        return $page;
    }

    /** @param list<string> $translatedLocales the languages every page here was written in */
    private function createProvider(array $pages, ?string $siteUrl = 'https://example.com', array $translatedLocales = ['fr']): PageDevProfilePathProvider
    {
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findAllOrdered')->willReturn($pages);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        return new PageDevProfilePathProvider(
            $repository,
            new PagePublicUrlResolver($configService, $this->createUrlGenerator(), $this->createSiteLocales(), $this->createPageTranslator($translatedLocales)),
            $this->createPageTranslator($translatedLocales),
            new PageHealthCheckTargets(
                $repository,
                new PagePublicUrlResolver($configService, $this->createUrlGenerator(), $this->createSiteLocales(), $this->createPageTranslator($translatedLocales)),
                $this->createStub(PageEditUrlResolver::class),
                $this->createPageTranslator($translatedLocales),
                $this->createSiteLocales(),
            ),
        );
    }

    public function testGetPathsReturnsOneLocalPathPerPublishedPage(): void
    {
        $provider = $this->createProvider([$this->createPage('home', 'Accueil'), $this->createPage('contact', 'Contact')]);

        $this->assertSame([
            ['path' => '/', 'label' => 'Accueil'],
            ['path' => '/pages/contact', 'label' => 'Contact'],
        ], $provider->getPaths());
    }

    // A page written in two languages is two pages to profile: read at "/en/pages/contact" it renders other blocks, other prose, other queries
    public function testGetPathsWalksEveryLanguageAPageWasWrittenIn(): void
    {
        $provider = $this->createProvider([$this->createPage('contact', 'Contact')], translatedLocales: ['fr', 'en']);

        $this->assertSame([
            ['path' => '/pages/contact', 'label' => 'Contact'],
            ['path' => '/en/pages/contact', 'label' => 'Contact (English)'],
        ], $provider->getPaths());
    }

    // The whole point of profiling locally: what's rendered is the developer's own kernel, so "site-url" (which points at the live site even from a dev environment) must play no part in the paths
    public function testGetPathsIgnoresSiteUrlEntirely(): void
    {
        $provider = $this->createProvider([$this->createPage('contact', 'Contact')], null);

        $this->assertSame([['path' => '/pages/contact', 'label' => 'Contact']], $provider->getPaths());
    }

    public function testGetPathsReturnsNothingWithoutAnyPublishedPage(): void
    {
        $this->assertSame([], $this->createProvider([])->getPaths());
    }
}
