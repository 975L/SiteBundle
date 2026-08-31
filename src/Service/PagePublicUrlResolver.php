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
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\SiteBundle\Entity\Page;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Builds a Page's canonical public URL - the single source of urls for every page-level HealthCheckProviderInterface implementation (PageSpeed, W3C, content quality) and for SitePageSitemapProvider (the sitemap), so they can't drift apart. The site root a site-wide check targets is ConfigBundle's SiteUrlResolver::siteRoot() instead, which spells it exactly as the home Page resolves here, so both land on a single row of the Health check dashboard. No trailing slash: both forms answer 200, so one of them has to be picked as canonical, and this is the one the sitemap has always declared. 'home' resolves to the site root, since PageController 301s "/pages/home" there - a sitemap must only ever list urls that answer 200, never a redirect hop. The path itself is generated through the router (PageController's page_home/page_display routes) rather than hand-built, so it can never drift from the real route definitions; only the host comes from "site-url" - the router's own RequestContext host can't be trusted here since this runs from a cron command, outside any HTTP request
class PagePublicUrlResolver
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly SiteLocales $siteLocales,
    ) {
    }

    /**
     * The same page in every language the site declares, itself included: what a "hreflang" group is made of, both
     * in the page's own head and in the sitemap.
     *
     * Empty on a site declaring a single language, which is every c975L site until it says otherwise - so nothing
     * is written anywhere, and a page keeps the head and the sitemap entry it has always had.
     *
     * @return array<string, string> hreflang => absolute url
     */
    public function resolveAlternates(Page $page): array
    {
        return $this->alternatesFor(fn (string $locale): string => $this->resolvePath($page, $locale));
    }

    /**
     * The same group for a url no Page of its own answers: a "collection" block's item detail view, which is served
     * by its parent Page (see PageController::resolveCollectionDetail()) and would otherwise declare that Page's group.
     *
     * The slug is the one the writing language answers on, item slug included ("blog/mon-article"), the way
     * page_display already receives it.
     *
     * @return array<string, string> hreflang => absolute url
     */
    public function resolveAlternatesForSlug(string $slug): array
    {
        return $this->alternatesFor(fn (string $locale): string => $this->generate('page_display', $slug, $locale));
    }

    /**
     * One url per declared language, from whatever builds the path of one - empty on a site declaring a single
     * language, and while "site-url" is unconfigured, a group needing absolute urls.
     *
     * @param callable(string): string $path
     *
     * @return array<string, string> hreflang => absolute url
     */
    private function alternatesFor(callable $path): array
    {
        $siteUrl = $this->siteUrl();
        $locales = $this->siteLocales->all();

        if (null === $siteUrl || \count($locales) < 2) {
            return [];
        }

        $alternates = [];
        foreach ($locales as $locale) {
            $alternates[$locale] = $siteUrl . $path($locale);
        }

        return $alternates;
    }

    // Null if "site-url" isn't configured yet - every HealthCheckProvider using this treats that the same way (nothing to check)
    public function resolve(Page $page): ?string
    {
        $siteUrl = $this->siteUrl();

        return null === $siteUrl ? null : $siteUrl . $this->resolvePath($page);
    }

    // The configured host without its trailing slash, null when unconfigured - every path appended to it already opens with a slash, and a "site-url" saved as "https://example.com/" would otherwise double it
    private function siteUrl(): ?string
    {
        $siteUrl = trim((string) $this->configService->get('site-url'));

        return '' === $siteUrl ? null : rtrim($siteUrl, '/');
    }

    // The local part of that url, without the host - what PageDevProfilePathProvider hands to the kernel, since profiling the developer's own machine has nothing to do with "site-url" (which points at the live site even from a dev environment)
    public function resolvePath(Page $page, ?string $locale = null): string
    {
        return 'home' === $page->getSlug()
            ? $this->generate('page_home', null, $locale)
            : $this->generate('page_display', $page->getSlug(), $locale);
    }

    // One route in one language, through the router rather than by hand, so a path can never drift from the route definitions. The writing language keeps its urls byte for byte - the ones the sitemap has always declared, a translated page never moving the original (see PageController's routes)
    private function generate(string $route, ?string $slug, ?string $locale): string
    {
        $localized = null !== $locale && $locale !== $this->siteLocales->getDefaultLocale();
        $parameters = $localized ? ['_locale' => $locale] : [];
        if (null !== $slug) {
            $parameters['page'] = $slug;
        }

        return $this->urlGenerator->generate($localized ? $route . '_localized' : $route, $parameters, UrlGeneratorInterface::ABSOLUTE_PATH);
    }
}
