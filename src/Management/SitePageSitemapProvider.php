<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\SelfCheckedSitemapProviderInterface;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\PageServiceInterface;

// Declares the site's own pages (public/sitemap-site.xml) - SiteBundle's contribution to the sitemap, collected like any other bundle's by SitemapCreateCommand. Self-checked (see the interface): these urls already have ContentQualityHealthCheckProvider, which reports them in more detail than the generic declared-urls check DeclaredUrlsHealthCheckPass builds for every other sitemap
class SitePageSitemapProvider implements SelfCheckedSitemapProviderInterface
{
    public function __construct(
        private readonly PagePublicUrlResolver $pagePublicUrlResolver,
        private readonly PageServiceInterface $pageService,
    ) {
    }

    public function getSitemapName(): string
    {
        return 'site';
    }

    // Gets urls from database
    public function getUrls(): array
    {
        $pages = $this->pageService->findAll();

        // Urls for the pages
        $urls = [];
        foreach ($pages as $page) {
            // Filtered here rather than in PageRepository::findAllOrdered(), which is also used for public display and health checks - a non-indexable page is still a published page, and stays checked like any other
            if (!$page->isIndexable()) {
                continue;
            }

            // Built through PagePublicUrlResolver rather than by hand, so the sitemap declares the exact same canonical urls the health checks test - notably the site root for "home", whose "/pages/home" form only ever 301s there. Null when "site-url" isn't configured yet: a sitemap only accepts absolute urls, so there's nothing to declare
            $url = $this->pagePublicUrlResolver->resolve($page);
            if (null === $url) {
                continue;
            }

            $urls[] = [
                'loc' => $url,
                'lastmod' => date('Y-m-d', $page->getModification()->getTimestamp()),
                'changefreq' => $page->getChangeFrequency() ?? 'weekly',
                // Page::$priority is passed as-is on its own 0-10 scale, SitemapWriter converts it to the 0.0-1.0 the protocol accepts. Default 4 for a page that never set one, the middle-low priority a plain content page deserves
                'priority' => $page->getPriority() ?? 4,
                // Ignored by the sitemap, this is what SeoFilesWriter lists the pages in llms.txt from (see SitemapProviderInterface). The social network summary doubles as the meta description of the page, so it is already the one sentence describing it - a page without one is still listed, under its title alone
                'title' => $page->getTitle(),
                'description' => $page->getSummarySocialNetwork(),
            ];
        }

        return $urls;
    }
}
