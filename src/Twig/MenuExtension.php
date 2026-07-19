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
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\DefaultPagesImporter;
use c975L\UiBundle\Entity\Block;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Cache\ItemInterface;
use Symfony\Contracts\Cache\TagAwareCacheInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MenuExtension extends AbstractExtension
{
    // Keyed by pageId (string, as found in a "page:ID" target) - filled in bulk by preloadPages() so getMenuLinkUrl()/getMenuLinkLabel() resolve every link of a rendered menu from memory instead of one find() call per link
    private array $pageCache = [];

    // Per-location memoization: Navbar.html.twig calls menu_blocks('navbar') twice in the same render (once to check "is not empty", once to actually render) - keeps that to one lookup per location per request even when it's a cache hit below
    private array $menuBlocksCache = [];

    public function __construct(
        private readonly MenuRepository $menuRepository,
        private readonly PageRepository $pageRepository,
        private readonly LinkableRouteRegistry $linkableRouteRegistry,
        private readonly UrlGeneratorInterface $router,
        private readonly TranslatorInterface $translator,
        private readonly TagAwareCacheInterface $cache,
        private readonly ConfigServiceInterface $configService,
        private readonly DefaultPagesImporter $defaultPagesImporter,
        private readonly CopyrightExtension $copyrightExtension,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('menu_blocks', [$this, 'getMenuBlocks']),
            new TwigFunction('menu_link_url', [$this, 'getMenuLinkUrl']),
            new TwigFunction('menu_link_label', [$this, 'getMenuLinkLabel']),
            new TwigFunction('menu_link_is_copyright', [$this, 'isMenuLinkCopyright']),
        ];
    }

    // @return Collection<int, Block>
    public function getMenuBlocks(string $location): Collection
    {
        if (!array_key_exists($location, $this->menuBlocksCache)) {
            $blocks = new ArrayCollection($this->loadMenuBlocks($location));
            $this->preloadPages($blocks);
            $this->menuBlocksCache[$location] = $blocks;
        }

        return $this->menuBlocksCache[$location];
    }

    // Cross-request cache: a menu's items barely ever change but are read on every single page - cached as the Block entities themselves (invalidated by MenuCacheInvalidationListener whenever a "menu_link" Block is saved/removed). Safe to cache the entities directly here: a Menu can only ever contain "menu_link" blocks (see BlockRegistry's "menu"-context restriction on the picker), and that kind's own template (MenuLink.html.twig) never reads block.media/block.user - the only relations a Block carries - so those stay untouched, harmless lazy references through the cache round-trip
    private function loadMenuBlocks(string $location): array
    {
        return $this->cache->get('menu_' . $location, function (ItemInterface $item) use ($location): array {
            $item->expiresAfter(null);
            $item->tag(['menus_all']);

            return $this->menuRepository->findOneByLocation($location)?->getBlocks()->toArray() ?? [];
        });
    }

    // Resolves a "menu_link" block's raw target ("page:ID", "page:ID#anchor-blockId" or "route:NAME", see MenuLinkType) into an actual URL - empty string if it no longer resolves (page unpublished/deleted, route no longer registered by a LinkableRouteProviderInterface, or target never set on an incomplete block), so the template can skip rendering it
    public function getMenuLinkUrl(?string $target): string
    {
        if (null === $target || '' === $target) {
            return '';
        }

        $parsed = self::parseTarget($target);

        if ('page' === $parsed['type']) {
            $page = $this->resolvePage($parsed['pageId']);

            return null === $page || !$page->isPublished() || $page->isDeleted()
                ? ''
                : $this->router->generate('page_display', ['page' => $page->getSlug()]) . (null !== $parsed['fragment'] ? '#' . $parsed['fragment'] : '');
        }

        return 'route' === $parsed['type'] && null !== $parsed['value'] && $this->linkableRouteRegistry->has($parsed['value'])
            ? $this->router->generate($parsed['value'])
            : '';
    }

    public function getMenuLinkLabel(?string $target): string
    {
        if (null === $target || '' === $target) {
            return '';
        }

        $parsed = self::parseTarget($target);

        if ('page' === $parsed['type']) {
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

            return $sectionLabel ?? (string) $page->getTitle();
        }

        if ('route' === $parsed['type'] && null !== $parsed['value']) {
            $route = $this->linkableRouteRegistry->get($parsed['value']);

            return null === $route ? '' : $this->translator->trans($route['label'], [], $route['translation_domain']);
        }

        return '';
    }

    // True when $target's own label already is (or would be) the live-computed copyright notice - lets Footer.html.twig skip its own fallback "copyright" span instead of showing it twice
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

    // Single point of "type:value" (and, for a "page:ID#fragment" target, pageId/fragment) parsing - shared by every target reader above plus preloadPages() below
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
        $ids = [];
        foreach ($blocks as $block) {
            if ('menu_link' !== $block->getKind()) {
                continue;
            }

            $parsed = self::parseTarget((string) ($block->getData()['target'] ?? ''));
            if ('page' !== $parsed['type'] || null === $parsed['pageId']) {
                continue;
            }

            if (!array_key_exists($parsed['pageId'], $this->pageCache)) {
                $ids[$parsed['pageId']] = true;
            }
        }

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

    // Resolves a "<anchor>-<blockId>" fragment back to that block's own label - same title-or-anchor, strip_tags'd fallback as MenuLinkType's own picker choices, so both stay in sync. The anchor slug itself may contain hyphens, so the block's numeric id (always its trailing segment) is what's matched on, not a naive split on the first/last "-"
    private function findSectionLabel(Page $page, string $fragment): ?string
    {
        if (1 !== preg_match('/-(\d+)$/', $fragment, $matches)) {
            return null;
        }

        $blockId = (int) $matches[1];
        foreach ($page->getBlocks() as $block) {
            if ($block->getId() === $blockId) {
                $label = strip_tags((string) ($block->getData()['title'] ?? $block->getData()['anchor'] ?? ''));

                // Empty, not just missing, title/anchor (e.g. cleared after the menu_link was saved) - '' would short-circuit getMenuLinkLabel()'s own "?? $page->getTitle()" fallback
                return '' !== $label ? $label : null;
            }
        }

        return null;
    }
}
