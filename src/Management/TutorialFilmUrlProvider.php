<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\GuidedProjectBuilder;
use c975L\ConfigBundle\Management\TutorialFilmUrlProviderInterface;
use c975L\ConfigBundle\Security\Voter\BackOfficeAccessVoter;
use c975L\SiteBundle\Controller\Management\TutorialFilmController;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\TutorialCatalog;
use c975L\SiteBundle\Service\TutorialCollectionSourceProvider;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Router\AdminRouteGeneratorInterface;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Sends a guided project's "Watch the film" link to this site's own film: the back office's page of its own films for one only the back office shows, the public page showing them for the others when the site has one - the rest keep the ecosystem's (see TutorialFilmUrlProviderInterface)
class TutorialFilmUrlProvider implements TutorialFilmUrlProviderInterface
{
    // Whether a page holds the tutorials, asked once for the whole project list
    private ?bool $hasPage = null;

    public function __construct(
        private readonly TutorialCatalog $catalog,
        private readonly PageRepository $pageRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        private readonly GuidedProjectBuilder $guidedProjectBuilder,
        private readonly Security $security,
        private readonly AdminRouteGeneratorInterface $adminRouteGenerator,
        #[Autowire(param: 'kernel.default_locale')]
        private readonly string $defaultLocale,
    ) {
    }

    public function getFilmUrl(string $slug): ?string
    {
        $locale = $this->requestStack->getCurrentRequest()?->getLocale() ?? $this->defaultLocale;

        // A film only the back office shows comes first, unless it was found in the site's language and a public one speaks the reader's
        $private = $this->catalog->findPrivate($slug, $locale);
        if (null !== $private && ($locale === $private['locale'] || !$this->catalog->isFilmedIn($slug, $locale))) {
            $url = $this->privateFilmUrl($slug);
            if (null !== $url) {
                return $url;
            }
        }

        if (!$this->catalog->isFilmed($slug, $locale)) {
            return null;
        }

        $this->hasPage ??= null !== $this->pageRepository->findOneByCollectionSource(TutorialCollectionSourceProvider::SOURCE);

        return $this->hasPage ? $this->urlGenerator->generate('site_tutorial_film', ['slug' => $slug]) : null;
    }

    // The film's card on the back office's page, its anchor opening the dialog - only to a reader the page lets in and whose roles open the parcours, as the route serving its files asks (see TutorialFilmController::film()): an AI assistant answering a visitor reads every project (GuidedProjectBuilder::getAllProjects()). Null otherwise, or when no dashboard carries the page (the first dashboard asked, the ecosystem having one), the project then keeping the next link
    private function privateFilmUrl(string $slug): ?string
    {
        if (!$this->security->isGranted(BackOfficeAccessVoter::ACCESS) || !$this->guidedProjectBuilder->isGranted($slug)) {
            return null;
        }

        $route = $this->adminRouteGenerator->findRouteName(crudControllerFqcn: TutorialFilmController::class, actionName: 'index');

        return null === $route ? null : $this->urlGenerator->generate($route) . '#' . $slug;
    }
}
