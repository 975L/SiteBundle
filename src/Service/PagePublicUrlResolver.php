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

// Builds a Page's real public URL - shared by every HealthCheckProviderInterface implementation (PageSpeed, security headers, W3C) so they all target the exact same set of urls. Mirrors ContentAccessTest's routing (trailing slash, 'home' redirecting to the site root) rather than SitemapCreateCommand's (no trailing slash) - this is the URL a browser actually lands on, not an intermediate redirect hop. The path itself is generated through the router (PageController's page_home/page_display routes) rather than hand-built, so it can never drift from the real route definitions; only the host comes from "site-url" - the router's own RequestContext host can't be trusted here since this runs from a cron command, outside any HTTP request
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

        $path = 'home' === $page->getSlug()
            ? $this->urlGenerator->generate('page_home', [], UrlGeneratorInterface::ABSOLUTE_PATH)
            : $this->urlGenerator->generate('page_display', ['page' => $page->getSlug() . '/'], UrlGeneratorInterface::ABSOLUTE_PATH);

        return $siteUrl . $path;
    }
}
