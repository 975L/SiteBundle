<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\DevProfilePathProviderInterface;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\PageTranslator;
use Symfony\Component\DependencyInjection\Attribute\When;

// Declares every published page to c975l:dev-profile:run (see DevProfilePathProviderInterface), so the command profiles the same set of pages the health check, the smoke test and the sitemap all work from - the local path only, the pages being rendered by the local kernel, not fetched from the live site
#[When('dev')]
class PageDevProfilePathProvider implements DevProfilePathProviderInterface
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PagePublicUrlResolver $pagePublicUrlResolver,
        private readonly PageTranslator $pageTranslator,
        private readonly PageHealthCheckTargets $targets,
    ) {
    }

    public function getPaths(): array
    {
        // Paths and not urls, unlike every health check: profiling the developer's own machine has nothing to do with "site-url" - so the languages are walked here rather than through PageHealthCheckTargets
        $pages = $this->pageRepository->findAllOrdered();

        // Every page's translations in one query per language, rather than one per page and per language
        $this->pageTranslator->preload($pages);

        $paths = [];
        foreach ($pages as $page) {
            foreach ($this->pageTranslator->translatedLocales($page) as $locale) {
                $paths[] = [
                    'path' => $this->pagePublicUrlResolver->resolvePath($page, $locale),
                    // The title the developer wrote, in every language: what is profiled is the page's own rendering, and a row named in English would not be found in a list read while writing French
                    'label' => $this->targets->label($page, $locale, (string) $page->getTitle()),
                ];
            }
        }

        return $paths;
    }
}
