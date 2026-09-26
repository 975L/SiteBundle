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
use c975L\SiteBundle\Management\TutorialFilmUrlProvider;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\TutorialCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TutorialFilmUrlProviderTest extends TestCase
{
    private function provider(?Page $page): TutorialFilmUrlProvider
    {
        $catalog = $this->createStub(TutorialCatalog::class);
        $catalog->method('isFilmed')->willReturnCallback(static fn (string $slug): bool => 'site-own' === $slug);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findOneByCollectionSource')->willReturn($page);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(static fn (string $route, array $parameters): string => '/tutorials/film/' . $parameters['slug']);

        return new TutorialFilmUrlProvider($catalog, $pageRepository, $urlGenerator, new RequestStack(), 'fr');
    }

    // A film the site publishes, on a page showing them, is linked there
    public function testAFilmOfTheSiteIsLinkedOnTheSite(): void
    {
        $this->assertSame('/tutorials/film/site-own', $this->provider(new Page())->getFilmUrl('site-own'));
    }

    // No film of its own, or no page to show it: the ecosystem's link stays
    public function testOtherwiseTheLinkIsLeftToTheEcosystem(): void
    {
        $this->assertNull($this->provider(new Page())->getFilmUrl('site-page-creation'));
        $this->assertNull($this->provider(null)->getFilmUrl('site-own'));
    }
}
