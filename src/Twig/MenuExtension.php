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
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Entity\Block;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MenuExtension extends AbstractExtension
{
    public function __construct(
        private readonly MenuRepository $menuRepository,
        private readonly PageRepository $pageRepository,
        private readonly LinkableRouteRegistry $linkableRouteRegistry,
        private readonly UrlGeneratorInterface $router,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('menu_blocks', [$this, 'getMenuBlocks']),
            new TwigFunction('menu_link_url', [$this, 'getMenuLinkUrl']),
            new TwigFunction('menu_link_label', [$this, 'getMenuLinkLabel']),
        ];
    }

    // @return Collection<int, Block>
    public function getMenuBlocks(string $location): Collection
    {
        return $this->menuRepository->findOneByLocation($location)?->getBlocks() ?? new ArrayCollection();
    }

    // Resolves a "menu_link" block's raw target ("page:ID", "page:ID#anchor-blockId" or "route:NAME",
    // see MenuLinkType) into an actual URL - empty string if it no longer resolves (page
    // unpublished/deleted, route no longer registered by a LinkableRouteProviderInterface), so the
    // template can skip rendering it
    public function getMenuLinkUrl(string $target): string
    {
        [$type, $value] = array_pad(explode(':', $target, 2), 2, null);

        if ('page' === $type) {
            [$pageId, $fragment] = self::splitPageIdAndFragment($value);
            $page = $this->pageRepository->find($pageId);

            return null === $page || !$page->isPublished() || $page->isDeleted()
                ? ''
                : $this->router->generate('page_display', ['page' => $page->getSlug()]) . (null !== $fragment ? '#' . $fragment : '');
        }

        return 'route' === $type && null !== $value && $this->linkableRouteRegistry->has($value)
            ? $this->router->generate($value)
            : '';
    }

    public function getMenuLinkLabel(string $target): string
    {
        [$type, $value] = array_pad(explode(':', $target, 2), 2, null);

        if ('page' === $type) {
            [$pageId, $fragment] = self::splitPageIdAndFragment($value);
            $page = $this->pageRepository->find($pageId);
            if (null === $page) {
                return '';
            }

            // A "#anchor-blockId" fragment (see MenuLinkType) labels a specific section, not the page
            // itself - falls back to the page's own title if the block was since removed/moved
            $sectionLabel = null !== $fragment ? $this->findSectionLabel($page, $fragment) : null;

            return $sectionLabel ?? (string) $page->getTitle();
        }

        if ('route' === $type && null !== $value) {
            $route = $this->linkableRouteRegistry->get($value);

            return null === $route ? '' : $this->translator->trans($route['label'], [], $route['translation_domain']);
        }

        return '';
    }

    // @return array{0: ?string, 1: ?string} [pageId, fragment]
    private static function splitPageIdAndFragment(?string $value): array
    {
        return array_pad(explode('#', (string) $value, 2), 2, null);
    }

    // Resolves a "<anchor>-<blockId>" fragment back to that block's own label - same title-or-anchor,
    // strip_tags'd fallback as MenuLinkType's own picker choices, so both stay in sync. The anchor slug
    // itself may contain hyphens, so the block's numeric id (always its trailing segment) is what's
    // matched on, not a naive split on the first/last "-"
    private function findSectionLabel(Page $page, string $fragment): ?string
    {
        if (1 !== preg_match('/-(\d+)$/', $fragment, $matches)) {
            return null;
        }

        $blockId = (int) $matches[1];
        foreach ($page->getBlocks() as $block) {
            if ($block->getId() === $blockId) {
                $label = strip_tags((string) ($block->getData()['title'] ?? $block->getData()['anchor'] ?? ''));

                // Empty, not just missing, title/anchor (e.g. cleared after the menu_link was saved) -
                // '' would short-circuit getMenuLinkLabel()'s own "?? $page->getTitle()" fallback
                return '' !== $label ? $label : null;
            }
        }

        return null;
    }
}
