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
use Symfony\Component\DependencyInjection\Attribute\When;

// Declares every published page to c975l:dev-profile:run (see DevProfilePathProviderInterface), so the command profiles the same set of pages the health check, the smoke test and the sitemap all work from - the local path only, the pages being rendered by the local kernel, not fetched from the live site
#[When('dev')]
class PageDevProfilePathProvider implements DevProfilePathProviderInterface
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PagePublicUrlResolver $pagePublicUrlResolver,
    ) {
    }

    public function getPaths(): array
    {
        $paths = [];
        foreach ($this->pageRepository->findAllOrdered() as $page) {
            $paths[] = [
                'path' => $this->pagePublicUrlResolver->resolvePath($page),
                'label' => $page->getTitle(),
            ];
        }

        return $paths;
    }
}
