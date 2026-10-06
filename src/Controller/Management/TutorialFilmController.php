<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Controller\Management;

use c975L\ConfigBundle\Controller\Management\GuidedProjectController;
use c975L\ConfigBundle\Management\GuidedProjectBuilder;
use c975L\ConfigBundle\Security\Voter\BackOfficeAccessVoter;
use c975L\SiteBundle\Service\TutorialCatalog;
use c975L\SiteBundle\Service\TutorialCollectionSourceProvider;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Router\AdminRouteGeneratorInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

// The films only the back office shows: the page presenting them as the public tutorials page does, and the route serving their files
class TutorialFilmController extends AbstractController
{
    private const array CONTENT_TYPES = [
        'webm' => 'video/webm',
        'vtt' => 'text/vtt',
        'jpg' => 'image/jpeg',
    ];

    public function __construct(
        private readonly GuidedProjectBuilder $guidedProjectBuilder,
        private readonly TutorialCatalog $catalog,
        private readonly TutorialCollectionSourceProvider $collectionSource,
        private readonly AdminRouteGeneratorInterface $adminRouteGenerator,
    ) {
    }

    // The back office's own films, drawn by the very card and dialog of the public tutorials page - only those of the parcours the reader may follow, each guided project's "Watch the film" linking its anchor (see TutorialFilmUrlProvider::getFilmUrl()). Route names are asked of EasyAdmin, which prefixes them with the dashboard's own
    #[AdminRoute(path: '/tutorial-films', name: 'tutorial_films', options: ['methods' => ['GET']])]
    public function index(Request $request): Response
    {
        $this->denyAccessUnlessGranted(BackOfficeAccessVoter::ACCESS);

        $dashboard = $request->attributes->get(EA::DASHBOARD_CONTROLLER_FQCN);
        $filmRoute = (string) $this->adminRouteGenerator->findRouteName($dashboard, self::class, 'film');
        $tutorials = $this->catalog->allPrivate($request->getLocale(), fn (string $slug, string $locale, string $extension, int $version): string => $this->generateUrl($filmRoute, ['locale' => $locale, 'slug' => $slug, 'extension' => $extension, 'v' => $version]));
        $projectsRoute = $this->adminRouteGenerator->findRouteName($dashboard, GuidedProjectController::class, 'index');

        return $this->render('@c975LSite/management/tutorial_films.html.twig', [
            'items' => $this->collectionSource->items($tutorials, public: false),
            'itemTemplate' => TutorialCollectionSourceProvider::ITEM_TEMPLATE,
            'guidedProjectsUrl' => null === $projectsRoute ? null : $this->generateUrl($projectsRoute),
        ]);
    }

    // Serves a film only the back office shows, its subtitles and its poster, from private/medias/films: to whoever holds the role of its guided project (GuidedProjectBuilder::isGranted(), asked without building the projects since a <video> seeking fires a Range request after another). The locale is in the path so the url names the very file served
    #[AdminRoute(
        path: '/tutorial-film/{locale}/{slug}.{extension}',
        name: 'tutorial_film',
        options: ['methods' => ['GET'], 'requirements' => ['locale' => '[a-z]{2}(_[A-Z]{2})?', 'slug' => '[a-z0-9-]+', 'extension' => 'webm|vtt|jpg']],
    )]
    public function film(Request $request, string $locale, string $slug, string $extension): BinaryFileResponse
    {
        $this->denyAccessUnlessGranted(BackOfficeAccessVoter::ACCESS);

        $film = $this->guidedProjectBuilder->isGranted($slug) ? $this->catalog->findPrivate($slug, $locale) : null;
        $file = $locale === ($film['locale'] ?? null) ? $this->catalog->privateFile($slug, $locale, $extension) : null;
        if (null === $file || !is_file($file)) {
            throw $this->createNotFoundException();
        }

        // Revalidated on each reading, so an account losing the project's role loses the film too: a 304 while the file has not moved (Last-Modified, set by BinaryFileResponse). No ETag, which would hash the whole film on every Range request
        $response = new BinaryFileResponse($file, headers: ['Content-Type' => self::CONTENT_TYPES[$extension]]);
        $response->setPrivate();
        $response->headers->addCacheControlDirective('no-cache');
        $response->isNotModified($request);

        return $response;
    }
}
