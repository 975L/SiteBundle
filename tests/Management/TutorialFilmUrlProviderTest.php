<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\ConfigBundle\Management\GuidedProjectBuilder;
use c975L\SiteBundle\Controller\Management\TutorialFilmController;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\TutorialFilmUrlProvider;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\TutorialCatalog;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Router\AdminRouteGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class TutorialFilmUrlProviderTest extends TestCase
{
    // "site-own" has a public film, "back-office" a private one in French, "both" a private one in French and a public one in English. $backOffice stands for the back office's gate, $projectRole for GuidedProjectBuilder::isGranted(), $dashboard for whether a dashboard carries the films' page
    private function provider(?Page $page, bool $dashboard = true, bool $backOffice = true, bool $projectRole = true, string $locale = 'fr'): TutorialFilmUrlProvider
    {
        $catalog = $this->createStub(TutorialCatalog::class);
        $catalog->method('isFilmed')->willReturnCallback(static fn (string $slug): bool => \in_array($slug, ['site-own', 'both'], true));
        $catalog->method('isFilmedIn')->willReturnCallback(static fn (string $slug, string $locale): bool => 'site-own' === $slug || ('both' === $slug && 'en' === $locale));
        $catalog->method('findPrivate')->willReturnCallback(static fn (string $slug): ?array => \in_array($slug, ['back-office', 'both'], true) ? ['locale' => 'fr', 'version' => 1759000000, 'narrated' => true] : null);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findOneByCollectionSource')->willReturn($page);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(static fn (string $route, array $parameters = []): string => 'site_tutorial_film' === $route ? '/tutorials/film/' . $parameters['slug'] : '/' . $route);

        $request = new Request();
        $request->setLocale($locale);
        $requestStack = new RequestStack([$request]);

        $builder = $this->createStub(GuidedProjectBuilder::class);
        $builder->method('isGranted')->willReturn($projectRole);
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($backOffice);

        // Named as a dashboard called "admin" names it, none being written "management_..."
        $adminRouteGenerator = $this->createStub(AdminRouteGeneratorInterface::class);
        $adminRouteGenerator->method('findRouteName')->willReturnCallback(static fn (?string $dashboardFqcn, string $controller, string $action): ?string => $dashboard && TutorialFilmController::class === $controller ? 'admin_tutorial_films' : null);

        return new TutorialFilmUrlProvider($catalog, $pageRepository, $urlGenerator, $requestStack, $builder, $security, $adminRouteGenerator, 'fr');
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

    // A film only the back office shows is linked to its card on the back office's page, under whatever name the dashboard gives it, with no public page needed
    public function testABackOfficeFilmIsLinkedToTheBackOfficePage(): void
    {
        $this->assertSame('/admin_tutorial_films#back-office', $this->provider(null)->getFilmUrl('back-office'));
    }

    // No dashboard carries the page: the project keeps the ecosystem's link
    public function testABackOfficeFilmWithoutItsPageKeepsTheEcosystemLink(): void
    {
        $this->assertNull($this->provider(null, false)->getFilmUrl('back-office'));
    }

    // A reader the back office keeps out, or lacking the parcours' role, is not sent to a page that would not show them the film
    public function testABackOfficeFilmIsOnlyLinkedForWhoeverMayWatchIt(): void
    {
        $this->assertNull($this->provider(null, backOffice: false)->getFilmUrl('back-office'));
        $this->assertNull($this->provider(null, projectRole: false)->getFilmUrl('back-office'));
    }

    // Short of the back office's page, a public film of the same project still answers
    public function testThePublicFilmStandsInForAnUnreachableBackOfficeOne(): void
    {
        $this->assertSame('/tutorials/film/both', $this->provider(new Page(), false)->getFilmUrl('both'));
        $this->assertSame('/tutorials/film/both', $this->provider(new Page(), projectRole: false)->getFilmUrl('both'));
    }

    // A private film found in the site's language gives way to a public one speaking the reader's, not to one in the site's
    public function testAPublicFilmInTheReaderLanguageOutranksAFallbackPrivateOne(): void
    {
        $this->assertSame('/tutorials/film/both', $this->provider(new Page(), locale: 'en')->getFilmUrl('both'));
        $this->assertSame('/admin_tutorial_films#both', $this->provider(new Page())->getFilmUrl('both'));
    }
}
