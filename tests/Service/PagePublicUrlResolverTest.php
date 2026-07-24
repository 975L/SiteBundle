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

    public function testResolveReturnsNullWithoutASiteUrl(): void
    {
        $resolver = new PagePublicUrlResolver($this->createConfigService(null), $this->createUrlGenerator());

        $this->assertNull($resolver->resolve($this->createPage('contact')));
    }

    public function testResolveBuildsTheSiteRootForHome(): void
    {
        $resolver = new PagePublicUrlResolver($this->createConfigService('https://example.com'), $this->createUrlGenerator());

        $this->assertSame('https://example.com/', $resolver->resolve($this->createPage('home')));
    }

    public function testResolveBuildsARegularPageUrlWithTrailingSlash(): void
    {
        $resolver = new PagePublicUrlResolver($this->createConfigService('https://example.com'), $this->createUrlGenerator());

        $this->assertSame('https://example.com/pages/contact/', $resolver->resolve($this->createPage('contact')));
    }
}
