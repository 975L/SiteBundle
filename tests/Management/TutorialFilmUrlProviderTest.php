<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Controller\Management\TutorialFilmController;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\TutorialFilmUrlProvider;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\TutorialCatalog;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\RouteNotFoundException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TutorialFilmUrlProviderTest extends TestCase
{
    private function provider(?Page $page, bool $managementRoute = true): TutorialFilmUrlProvider
    {
        $catalog = $this->createStub(TutorialCatalog::class);
        $catalog->method('isFilmed')->willReturnCallback(static fn (string $slug): bool => 'site-own' === $slug);
        $catalog->method('findPrivate')->willReturnCallback(static fn (string $slug): ?array => 'back-office' === $slug ? ['locale' => 'fr', 'version' => 1759000000, 'narrated' => true] : null);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findOneByCollectionSource')->willReturn($page);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(static fn (string $route, array $parameters): string => match (true) {
            TutorialFilmController::ROUTE !== $route => '/tutorials/film/' . $parameters['slug'],
            $managementRoute => sprintf('/management/tutorial-film/%s/%s.%s?v=%d', $parameters['locale'], $parameters['slug'], $parameters['extension'], $parameters['v']),
            default => throw new RouteNotFoundException(),
        });

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

    // A film only the back office shows is played in place, from the route checking its project's role, with no page needed
    public function testABackOfficeFilmIsPlayedFromTheManagementRoute(): void
    {
        $this->assertSame([
            'video' => '/management/tutorial-film/fr/back-office.webm?v=1759000000',
            'subtitles' => '/management/tutorial-film/fr/back-office.vtt?v=1759000000',
            'poster' => '/management/tutorial-film/fr/back-office.jpg?v=1759000000',
            'locale' => 'fr',
            'narrated' => true,
        ], $this->provider(null)->getFilmPlayer('back-office'));
        $this->assertNull($this->provider(new Page())->getFilmPlayer('site-own'));
    }

    // A dashboard not named "management" has no film route: the projects' page keeps working, the film falling back to its link
    public function testAFilmWithoutItsRouteIsNotPlayed(): void
    {
        $this->assertNull($this->provider(null, false)->getFilmPlayer('back-office'));
    }
}
