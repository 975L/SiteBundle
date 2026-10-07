<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Service\SiteLocales;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PageEditUrlResolver;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\PageTranslator;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Intl\Locales;
use Symfony\Component\Security\Core\Authorization\Voter\AuthenticatedVoter;
use Symfony\Component\Security\Http\AccessMapInterface;

// What every page-level health check walks: one row per page and per language it was written in, rather than one row per page. A page read at "/en/pages/nos-ateliers" is another page to a crawler, a validator and a performance report - another title, another prose, another set of links - and a single row could only ever report one of them. Held here rather than repeated in each provider: the url, the label and the link back to the right language screen are the same three things whichever check is running
class PageHealthCheckTargets
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PagePublicUrlResolver $pagePublicUrlResolver,
        private readonly PageEditUrlResolver $pageEditUrlResolver,
        private readonly PageTranslator $pageTranslator,
        private readonly SiteLocales $siteLocales,
        private readonly ?AccessMapInterface $accessMap = null,
    ) {
    }

    // Every page in every language it says something in. Throws rather than giving back nothing when "site-url" is unconfigured: these kinds are exhaustive, so an empty run tells HealthCheckRunner every stored row is stale and clears them - and an unconfigured site url says nothing about the pages already checked. The runner catches it and leaves the kind untouched.
    /** @return list<array{url: string, label: string, editUrl: string, locale: string, page: Page}> */
    public function all(): array
    {
        $pages = $this->pageRepository->findAllOrdered();

        // Every page's translations in one query per language, resolveAll() below asking them of each page in turn
        $this->pageTranslator->preload($pages);

        $targets = [];
        foreach ($pages as $page) {
            $urls = $this->pagePublicUrlResolver->resolveAll($page);
            if ([] === $urls) {
                throw new \RuntimeException('Site url is not configured: no page url can be resolved.');
            }

            foreach ($urls as $locale => $url) {
                if (!$this->isPublic($url)) {
                    continue;
                }

                $targets[] = [
                    'url' => $url,
                    'label' => $this->label($page, $locale),
                    'editUrl' => $this->pageEditUrlResolver->resolve($page, $locale),
                    'locale' => $locale,
                    'page' => $page,
                ];
            }
        }

        return $targets;
    }

    // Whether an anonymous visitor may read the url, as the firewall's access_control says. A page a rule keeps for members answers the checks with the login form or a redirect to it - judging that form under the page's name reported a missing description and a redirect on every private page of a members' site, about a page no check can ever see
    private function isPublic(string $url): bool
    {
        $attributes = $this->accessMap?->getPatterns(Request::create($url))[0] ?? null;

        return null === $attributes || [] === array_diff($attributes, [AuthenticatedVoter::PUBLIC_ACCESS]);
    }

    // The page's own title in the language the row is about, named in that language's own words where it is not the one the site is written in - "Nos ateliers" and "Our workshops (English)" being two rows of the same dashboard, told apart at a glance. Public, so the dev-profile paths - which walk the languages themselves, having no use for "site-url" - name their rows the same way
    public function label(Page $page, string $locale, ?string $title = null): string
    {
        $title ??= (string) ($this->pageTranslator->value($page, $locale, 'title') ?? $page->getTitle());

        // Named as a label names it, not as a sentence does: the same reading CoreBundle's "language_name" makes of Intl's own lowercase form
        return $locale === $this->siteLocales->getDefaultLocale()
            ? $title
            : sprintf('%s (%s)', $title, mb_ucfirst(Locales::getName($locale, $locale)));
    }
}
