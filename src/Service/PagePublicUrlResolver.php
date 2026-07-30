<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Entity\Page;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Builds a Page's canonical public URL - the single source of urls for every HealthCheckProviderInterface implementation (PageSpeed, security headers, W3C) and for SitePageSitemapProvider (the sitemap), so they can't drift apart. No trailing slash: both forms answer 200, so one of them has to be picked as canonical, and this is the one the sitemap has always declared. 'home' resolves to the site root, since PageController 301s "/pages/home" there - a sitemap must only ever list urls that answer 200, never a redirect hop. The path itself is generated through the router (PageController's page_home/page_display routes) rather than hand-built, so it can never drift from the real route definitions; only the host comes from "site-url" - the router's own RequestContext host can't be trusted here since this runs from a cron command, outside any HTTP request
class PagePublicUrlResolver
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    // Null if "site-url" isn't configured yet - every HealthCheckProvider using this treats that the same way (nothing to check)
    public function resolve(Page $page): ?string
    {
        $siteUrl = $this->configService->get('site-url');
        if (!$siteUrl) {
            return null;
        }

        return $siteUrl . $this->resolvePath($page);
    }

    // The local part of that url, without the host - what PageDevProfilePathProvider hands to the kernel, since profiling the developer's own machine has nothing to do with "site-url" (which points at the live site even from a dev environment)
    public function resolvePath(Page $page): string
    {
        return 'home' === $page->getSlug()
            ? $this->urlGenerator->generate('page_home', [], UrlGeneratorInterface::ABSOLUTE_PATH)
            : $this->urlGenerator->generate('page_display', ['page' => $page->getSlug()], UrlGeneratorInterface::ABSOLUTE_PATH);
    }
}
