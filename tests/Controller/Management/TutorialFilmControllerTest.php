<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Management\GuidedProjectBuilder;
use c975L\SiteBundle\Controller\Management\TutorialFilmController;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\TutorialCatalog;
use c975L\SiteBundle\Service\TutorialCollectionSourceProvider;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Router\AdminRouteGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

class TutorialFilmControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private string $private;

    protected function setUp(): void
    {
        $this->private = sys_get_temp_dir() . '/tutorial-film-' . uniqid();
        mkdir($this->private . '/medias/films/fr', 0o775, true);
        file_put_contents($this->private . '/medias/films/fr/films.json', json_encode(['back-office' => ['version' => 1759000000, 'starts' => [2.0]]]));
        file_put_contents($this->private . '/medias/films/fr/back-office.webm', 'webm');
    }

    protected function tearDown(): void
    {
        new Filesystem()->remove($this->private);
    }

    // $granted stands for what GuidedProjectBuilder::isGranted() answers for the film's project, and for whether getProjects() lists it - $backOffice for the back office's own gate. The page's view comes back as its template and parameters, to be asserted on
    private function controller(bool $granted = true, bool $backOffice = true): TutorialFilmController
    {
        $builder = $this->createStub(GuidedProjectBuilder::class);
        $builder->method('isGranted')->willReturn($granted);
        $builder->method('getProjects')->willReturn($granted ? [['slug' => 'back-office', 'label' => 'Ajouter un résistant', 'description' => '', 'steps' => [['label' => 'Ouvrir']]]] : []);

        $catalog = new TutorialCatalog($builder, sys_get_temp_dir(), 'fr', $this->private);
        $collectionSource = new TutorialCollectionSourceProvider($catalog, $this->createStub(PageRepository::class), new RequestStack(), 'fr');

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $route, array $parameters = []): string => [] === $parameters ? '/' . $route : sprintf('/%s/%s.%s?v=%d', $route, $parameters['slug'], $parameters['extension'], $parameters['v']));
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(static fn (string $view, array $parameters = []): string => json_encode([$view, $parameters['itemTemplate'], array_map(static fn ($item): array => $item->data, $parameters['items']), $parameters['guidedProjectsUrl']]));

        // Route names as a dashboard called "admin" gives them, so none is written "management_..."
        $adminRouteGenerator = $this->createStub(AdminRouteGeneratorInterface::class);
        $adminRouteGenerator->method('findRouteName')->willReturnCallback(static fn (?string $dashboard, string $controller, string $action): string => TutorialFilmController::class === $controller ? 'admin_tutorial_' . $action : 'admin_guided_projects_' . $action);

        $controller = new TutorialFilmController($builder, $catalog, $collectionSource, $adminRouteGenerator);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker($backOffice),
            'router' => $router,
            'twig' => $twig,
        ]));

        return $controller;
    }

    // The page draws the reader's films with the public page's card, their files served by the route checking the project's role, with neither report link nor VideoObject - routes named after whatever the dashboard is called
    public function testThePageListsTheFilmsOfTheReaderProjects(): void
    {
        [$view, $itemTemplate, $items, $guidedProjectsUrl] = json_decode((string) $this->controller()->index(new Request())->getContent(), true);

        $this->assertSame('@c975LSite/management/tutorial_films.html.twig', $view);
        $this->assertSame(TutorialCollectionSourceProvider::ITEM_TEMPLATE, $itemTemplate);
        $this->assertSame(['back-office'], array_column(array_column($items, 'tutorial'), 'slug'));
        $this->assertSame('/admin_tutorial_film/back-office.webm?v=1759000000', $items[0]['tutorial']['video']);
        $this->assertSame('/admin_guided_projects_index', $guidedProjectsUrl);
        $this->assertFalse($items[0]['reportable']);
        $this->assertFalse($items[0]['public']);
    }

    // The page links its own sheet, which has to be compiled (see sass/management-tutorials.scss)
    public function testThePageSheetIsShipped(): void
    {
        $this->assertFileExists(\dirname(__DIR__, 3) . '/public/css/management-tutorials.min.css', 'The back office tutorials sass has not been compiled.');
    }

    // A film whose project the reader may not follow stays off the page, as its files stay out of reach
    public function testThePageLeavesOutTheProjectsOutOfReach(): void
    {
        [, , $items] = json_decode((string) $this->controller(false)->index(new Request())->getContent(), true);

        $this->assertSame([], $items);
    }

    // The back office's own gate keeps the page too
    public function testNobodyOutsideTheBackOfficeSeesThePage(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->controller(true, false)->index(new Request());
    }

    // The film is read off private/ and served with its own content type, revalidated on each reading
    public function testTheFilmIsServedToWhoeverHoldsItsProjectRole(): void
    {
        $response = $this->controller()->film(new Request(), 'fr', 'back-office', 'webm');

        $this->assertSame($this->private . '/medias/films/fr/back-office.webm', $response->getFile()->getPathname());
        $this->assertSame('video/webm', $response->headers->get('Content-Type'));
        $this->assertTrue($response->headers->hasCacheControlDirective('no-cache'));
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    // A browser holding the file as it stands gets a 304, once the role is checked again
    public function testAnUnchangedFilmIsNotSentAgain(): void
    {
        $request = new Request(server: ['HTTP_IF_MODIFIED_SINCE' => gmdate('D, d M Y H:i:s', (int) filemtime($this->private . '/medias/films/fr/back-office.webm')) . ' GMT']);

        $this->assertSame(304, $this->controller()->film($request, 'fr', 'back-office', 'webm')->getStatusCode());
    }

    // The project's role is what keeps a film from the accounts it was not filmed for
    public function testAProjectOutOfTheUserReachHasNoFilm(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller(false)->film(new Request(), 'fr', 'back-office', 'webm');
    }

    // The url names the very file served: a locale without its own film does not get the site's
    public function testALocaleWithoutItsOwnFilmIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller()->film(new Request(), 'en', 'back-office', 'webm');
    }

    // A file the film lacks (here its subtitles) is a 404, not a 500
    public function testAMissingFileIsNotFound(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $this->controller()->film(new Request(), 'fr', 'back-office', 'vtt');
    }

    // The back office's own gate comes before any project's role
    public function testNobodyOutsideTheBackOfficeIsServed(): void
    {
        $this->expectException(AccessDeniedException::class);

        $this->controller(true, false)->film(new Request(), 'fr', 'back-office', 'webm');
    }
}
