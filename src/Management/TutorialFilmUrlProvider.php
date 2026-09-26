<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\TutorialFilmUrlProviderInterface;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\TutorialCatalog;
use c975L\SiteBundle\Service\TutorialCollectionSourceProvider;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Sends a guided project's "Watch the film" link to this site's own film, when the site publishes one and has a page showing them - the others keep the ecosystem's (see TutorialFilmUrlProviderInterface)
class TutorialFilmUrlProvider implements TutorialFilmUrlProviderInterface
{
    // Whether a page holds the tutorials, asked once for the whole project list
    private ?bool $hasPage = null;

    public function __construct(
        private readonly TutorialCatalog $catalog,
        private readonly PageRepository $pageRepository,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly RequestStack $requestStack,
        #[Autowire(param: 'kernel.default_locale')]
        private readonly string $defaultLocale,
    ) {
    }

    public function getFilmUrl(string $slug): ?string
    {
        if (!$this->catalog->isFilmed($slug, $this->requestStack->getCurrentRequest()?->getLocale() ?? $this->defaultLocale)) {
            return null;
        }

        $this->hasPage ??= null !== $this->pageRepository->findOneByCollectionSource(TutorialCollectionSourceProvider::SOURCE);

        return $this->hasPage ? $this->urlGenerator->generate('site_tutorial_film', ['slug' => $slug]) : null;
    }
}
