<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\SiteFormPageUrlProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SiteFormPageUrlProviderTest extends TestCase
{
    // Builds the provider over a repository answering $page for any form name, and a url generator echoing the slug it was given
    private function createProvider(?Page $page): SiteFormPageUrlProvider
    {
        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findOneByFormBlockName')->willReturn($page);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters = []): string => '/' . $route . '/' . ($parameters['page'] ?? '')
        );

        return new SiteFormPageUrlProvider($pageRepository, $urlGenerator);
    }

    // The url of the page actually carrying the "form" block, so a form posts to its admin-editable slug rather than to the bare "ui_form_submit" route
    public function testReturnsTheUrlOfThePageCarryingTheForm(): void
    {
        $page = new Page()->setTitle('Contact')->setSlug('contact');

        $this->assertSame('/page_display/contact', $this->createProvider($page)->getFormPageUrl('contact'));
    }

    // No page holds that form (not seeded yet, deleted, or another locale's) - null, which is what UiBundle falls back on
    public function testReturnsNullWhenNoPageCarriesTheForm(): void
    {
        $this->assertNull($this->createProvider(null)->getFormPageUrl('contact'));
    }
}
