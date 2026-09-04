<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Twig;

use c975L\ConfigBundle\Management\LinkableRouteRegistry;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Twig\CopyrightExtension;
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\DefaultPagesImporter;
use c975L\SiteBundle\Service\PageTranslator;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Service\BlockAnchorCollector;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Exception\ExceptionInterface as RoutingExceptionInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Twig\Attribute\AsTwigFunction;

class MenuExtension
{
    // Keyed by pageId (string, as found in a "page:ID" target) - filled in bulk by preloadPages() so getMenuLinkUrl()/getMenuLinkLabel() resolve every link of a rendered menu from memory instead of one find() call per link
    private array $pageCache = [];

    // Per-location memoization: Navbar.html.twig calls menu_blocks('navbar') twice in the same render (once to check "is not empty", once to actually render) - keeps that to one lookup per location per request even when it's a cache hit below
    private array $menuBlocksCache = [];

    // Keyed by pageId (string) - the "fragment => label" map of a target page's own anchors, see findSectionLabel()
    private array $pageAnchorsCache = [];

    // Per-location memoization of the layout an admin picked, same reasoning as $menuBlocksCache above
    private array $menuStyleCache = [];

    // Per-location memoization of the Menu row itself, keyed by location and holding the null of a location no menu was created for - the two cache entries above are read under keys of their own, so a request finding both cold (right after a menu was saved, or after a deploy) went through findOneByLocation() twice for the same footer, and that query joins the menu's blocks, their slots and everyone's medias
    /** @var array<string, ?Menu> */
    private array $menuCache = [];

    public function __construct(
        private readonly MenuRepository $menuRepository,
        private readonly PageRepository $pageRepository,
        private readonly LinkableRouteRegistry $linkableRouteRegistry,
        private readonly UrlGeneratorInterface $router,
        private readonly TagAwareCacheInterface $cache,
        private readonly ConfigServiceInterface $configService,
        private readonly DefaultPagesImporter $defaultPagesImporter,
        private readonly CopyrightExtension $copyrightExtension,
        private readonly BlockAnchorCollector $anchorCollector,
        private readonly RequestStack $requestStack,
        private readonly PageTranslator $pageTranslator,
        #[Autowire(param: 'kernel.default_locale')]
        private readonly string $defaultLocale = 'fr',
    ) {
    }

    // @return Collection<int, Block>
    #[AsTwigFunction('menu_blocks')]
    public function getMenuBlocks(string $location): Collection
    {
        if (!array_key_exists($location, $this->menuBlocksCache)) {
            $blocks = new ArrayCollection($this->loadMenuBlocks($location));
            $this->preloadPages($blocks);
            $this->menuBlocksCache[$location] = $blocks;
        }

        return $this->menuBlocksCache[$location];
    }

    // The layout an admin picked for that menu's items in the back-office (see Menu::STYLE_*), empty string when it was left to the site's theme - only the footer offers the choice, see MenuCrudController. Cached like the blocks are (its own key rather than a second value under theirs, so nothing has to deal with a cache entry saved in the previous shape), invalidated by the same tag
    #[AsTwigFunction('menu_style')]
    public function getMenuStyle(string $location): string
    {
        return $this->menuStyleCache[$location] ??= $this->cache->get('menu_style_' . $location, function (ItemInterface $item) use ($location): string {
            $item->expiresAfter(null);
            $item->tag(['menus_all']);

            return $this->findMenu($location)?->getStyle() ?? '';
        });
    }

    // Cross-request cache: a menu's items barely ever change but are read on every single page - cached as the Block entities themselves (invalidated by MenuCacheInvalidationListener whenever a "menu_link" or "menu_group" Block is saved/removed). The entities being cached whole, every relation a template may read has to be initialized before they go in, which is what MenuRepository::findOneByLocation() joins its slots and its medias for: a lazy collection cached uninitialized comes back detached and empty. Only "user" is left lazy, no menu template reading it
    private function loadMenuBlocks(string $location): array
    {
        return $this->cache->get('menu_' . $location, function (ItemInterface $item) use ($location): array {
            $item->expiresAfter(null);
            $item->tag(['menus_all']);

            return $this->findMenu($location)?->getBlocks()->toArray() ?? [];
        });
    }

    // The menu of a location, read at most once per request whatever the two cache entries above ask for
    private function findMenu(string $location): ?Menu
    {
        if (!array_key_exists($location, $this->menuCache)) {
            $this->menuCache[$location] = $this->menuRepository->findOneByLocation($location);
        }

        return $this->menuCache[$location];
    }

    // Resolves a "menu_link" block's raw target ("page:ID", "page:ID#anchor-blockId" or "route:NAME", see MenuLinkType) into an actual URL - empty string if it no longer resolves (page unpublished/deleted, route no longer registered by a LinkableRouteProviderInterface, or target never set on an incomplete block), so the template can skip rendering it
    #[AsTwigFunction('menu_link_url')]
    public function getMenuLinkUrl(?string $target): string
    {
        if (null === $target || '' === $target) {
            return '';
        }

        $parsed = self::parseTarget($target);

        return match ($parsed['type']) {
            'page' => $this->pageUrl($parsed),
            'route' => $this->routeUrl($parsed['value']),
            default => '',
        };
    }

    // Empty string for a page that no longer resolves (unpublished, soft-deleted, removed), so the template skips rendering the link
    private function pageUrl(array $parsed): string
    {
        $page = $this->resolvePage($parsed['pageId']);
        if (null === $page || !$page->isPublished() || $page->isDeleted()) {
            return '';
        }

        // The home page's only canonical url is the site root - PageController 301s "/pages/home" there, so going through page_display would cost a redirect hop on every single menu click (same rule as PagePublicUrlResolver and PageCrudController::pagePath()). Only the "home" slug: every other menu target keeps its own "/pages/{slug}" url
        // Read in another language, the whole menu is written in that language's urls: generating the writing language's ones would send the visitor back into it at the first click (PageController answers "/" and "/pages/{page}" in the writing language alone). The route attribute rather than getLocale(), which PageController switches back for the duration of the render
        // Only for a page really written in that language: a localised url answers for nothing else (PageController::requireTranslated() 404s it), so an untranslated page keeps the writing language's url rather than a link the visitor lands on a 404 from. The && short-circuits, so the writing language costs no lookup at all
        $locale = $this->requestStack->getCurrentRequest()?->attributes->get('_locale');
        $localized = \is_string($locale) && '' !== $locale && $locale !== $this->defaultLocale
            && \in_array($locale, $this->pageTranslator->translatedLocales($page), true);
        $parameters = $localized ? ['_locale' => $locale] : [];

        $path = 'home' === $page->getSlug()
            ? $this->router->generate($localized ? 'page_home_localized' : 'page_home', $parameters)
            : $this->router->generate($localized ? 'page_display_localized' : 'page_display', $parameters + ['page' => $page->getSlug()]);

        return $path . (null !== $parsed['fragment'] ? '#' . $parsed['fragment'] : '');
    }

    // Only a route a LinkableRouteProviderInterface still declares is generated - one dropped since the menu was saved yields an empty string rather than a routing exception on every page
    private function routeUrl(?string $route): string
    {
        $entry = null === $route ? null : $this->linkableRouteRegistry->get($route);
        if (null === $entry) {
            return '';
        }

        // Generated rather than stored: an entry standing for a database row (a gallery category...) carries the parameters it is reached by, read again at each render, so renaming that row's slug keeps the item pointing at it. Caught for the same reason as an entry dropped from the registry: an entry whose route was renamed, or whose parameters no longer fit its placeholders, drops the item rather than 500-ing every page through the navbar
        try {
            return $this->router->generate($entry['route'], $entry['params']);
        } catch (RoutingExceptionInterface) {
            return '';
        }
    }

    #[AsTwigFunction('menu_link_label')]
    public function getMenuLinkLabel(?string $target): string
    {
        if (null === $target || '' === $target) {
            return '';
        }

        $parsed = self::parseTarget($target);

        return match ($parsed['type']) {
            'page' => $this->pageLabel($parsed),
            'route' => $this->routeLabel($parsed['value']),
            default => '',
        };
    }

    private function pageLabel(array $parsed): string
    {
        $page = $this->resolvePage($parsed['pageId']);
        if (null === $page) {
            return '';
        }

        // No fragment (whole-page target) and this is the site's own "Copyright" legal page: shows the live computed copyright instead of the page's own title, so a footer's "Copyright" link doubles as the copyright notice instead of showing both side by side (see "site-menu-link-copyright-auto")
        if (null === $parsed['fragment'] && $this->isCopyrightPage($page)) {
            return $this->copyrightExtension->getCopyright(false);
        }

        // A "#anchor-blockId" fragment (see MenuLinkType) labels a specific section, not the page itself - falls back to the page's own title if the block was since removed/moved
        $sectionLabel = null !== $parsed['fragment'] ? $this->findSectionLabel($page, $parsed['fragment']) : null;

        // Through PageTranslator rather than the raw column: an item taking its label from the page it points at reads that page's title in the language being read, the same as the page's own <h1> does
        return $sectionLabel ?? $this->pageTranslator->getTitle($page);
    }

    private function routeLabel(?string $route): string
    {
        return null === $route ? '' : $this->linkableRouteRegistry->label($route);
    }

    // True when $target's own label already is (or would be) the live-computed copyright notice - lets Footer.html.twig skip its own fallback "copyright" span instead of showing it twice
    #[AsTwigFunction('menu_link_is_copyright')]
    public function isMenuLinkCopyright(?string $target): bool
    {
        if (null === $target || '' === $target) {
            return false;
        }

        $parsed = self::parseTarget($target);
        if ('page' !== $parsed['type']) {
            return false;
        }

        $page = $this->resolvePage($parsed['pageId']);

        return null !== $page && null === $parsed['fragment'] && $this->isCopyrightPage($page);
    }

    // Whether $page is the site's own "Copyright" legal page (see DefaultPagesImporter's "france/copyright" model), gated by the "site-menu-link-copyright-auto" config
    private function isCopyrightPage(Page $page): bool
    {
        return (bool) $this->configService->get('site-menu-link-copyright-auto')
            && $page->getSlug() === ($this->defaultPagesImporter->getLegalPageSlugsByModel()['france/copyright'] ?? null);
    }

    // Single point of "type:value" parsing, shared by every target reader above
    // @return array{type: ?string, value: ?string, pageId: ?string, fragment: ?string}
    private static function parseTarget(?string $target): array
    {
        [$type, $value] = array_pad(explode(':', (string) $target, 2), 2, null);
        [$pageId, $fragment] = 'page' === $type ? array_pad(explode('#', (string) $value, 2), 2, null) : [null, null];

        return ['type' => $type, 'value' => $value, 'pageId' => $pageId, 'fragment' => $fragment];
    }

    // Batches every "menu_link" block's target Page into a single query instead of one find() call per link (see resolvePage()) - parses each block's raw target the same way getMenuLinkUrl()/getMenuLinkLabel() do, but only to collect ids upfront
    private function preloadPages(Collection $blocks): void
    {
        $ids = $this->collectPageIds($blocks);
        if ([] === $ids) {
            return;
        }

        foreach ($this->pageRepository->findBy(['id' => array_keys($ids)]) as $page) {
            $this->pageCache[(string) $page->getId()] = $page;
        }

        // Any id that yielded no row (deleted since the menu was saved) still needs a cache entry, otherwise resolvePage() would retry it with an individual find() on every call
        foreach (array_keys($ids) as $pageId) {
            $this->pageCache[$pageId] ??= null;
        }
    }

    // The ids of every "page:ID" target of the given blocks not already cached, as keys (deduped) - a block of another kind, a "route:" target or an incomplete one contributes nothing
    private function collectPageIds(Collection $blocks): array
    {
        $ids = [];
        foreach ($blocks as $block) {
            // A container's own slots hold links too (a footer's links grouped in a "menu_group"), and one left out here would fall back to an individual find() at render time, which is exactly what this batch exists to avoid - the ids being keys, the union keeps them deduped
            $ids += $this->collectPageIds($block->getSlots());

            if ('menu_link' !== $block->getKind()) {
                continue;
            }

            $parsed = self::parseTarget((string) ($block->getData()['target'] ?? ''));
            if ('page' === $parsed['type'] && null !== $parsed['pageId'] && !array_key_exists($parsed['pageId'], $this->pageCache)) {
                $ids[$parsed['pageId']] = true;
            }
        }

        return $ids;
    }

    // Single point of Page lookup for both getMenuLinkUrl()/getMenuLinkLabel() - reads from the batch preloaded by preloadPages(), falling back to an individual find() for a target reached without going through getMenuBlocks() first (defensive; every current caller does)
    private function resolvePage(?string $pageId): ?Page
    {
        if (null === $pageId) {
            return null;
        }

        return array_key_exists($pageId, $this->pageCache)
            ? $this->pageCache[$pageId]
            : $this->pageCache[$pageId] = $this->pageRepository->find($pageId);
    }

    // Resolves an in-page fragment back to the label of the block declaring it - through the very collector MenuLinkType builds its picker choices with, so both stay in sync (nested container slots and "slug"-based ids included). Memoized per page: a menu often carries several anchors of the same page, each one otherwise re-walking its whole block tree. Null for a fragment no block declares any more (section removed since the menu_link was saved), which getMenuLinkLabel() falls back from to the page's own title
    private function findSectionLabel(Page $page, string $fragment): ?string
    {
        $anchors = $this->pageAnchorsCache[(string) $page->getId()] ??= $this->anchorCollector->collect($page->getBlocks());

        return $anchors[$fragment] ?? null;
    }
}
