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
use c975L\SiteBundle\Entity\MenuItem;
use c975L\SiteBundle\Repository\MenuItemRepository;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class MenuExtension extends AbstractExtension
{
    public function __construct(
        private readonly MenuItemRepository $menuItemRepository,
        private readonly LinkableRouteRegistry $linkableRouteRegistry,
        private readonly UrlGeneratorInterface $router,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('menu_items', [$this, 'getMenuItems']),
            new TwigFunction('menu_item_url', [$this, 'getMenuItemUrl']),
            new TwigFunction('menu_item_label', [$this, 'getMenuItemLabel']),
        ];
    }

    // Items for a given location, checked live: a page item disappears once its page is
    // unpublished/deleted, a route item disappears once its route is no longer registered
    // (e.g. the contributing bundle got removed) - see LinkableRouteProviderInterface
    // @return MenuItem[]
    public function getMenuItems(string $location): array
    {
        $items = $this->menuItemRepository->findByLocation($location);

        return array_values(array_filter($items, function (MenuItem $item): bool {
            if (null !== $item->getPage()) {
                return $item->getPage()->isPublished() && !$item->getPage()->isDeleted();
            }

            return null !== $item->getRoute() && $this->linkableRouteRegistry->has($item->getRoute());
        }));
    }

    public function getMenuItemUrl(MenuItem $item): string
    {
        if (null !== $item->getPage()) {
            return $this->router->generate('page_display', ['page' => $item->getPage()->getSlug()]);
        }

        return $this->router->generate($item->getRoute());
    }

    public function getMenuItemLabel(MenuItem $item): string
    {
        if (null !== $item->getPage()) {
            return (string) $item->getPage()->getTitle();
        }

        $route = $this->linkableRouteRegistry->get($item->getRoute());

        return null === $route ? '' : $this->translator->trans($route['label'], [], $route['translation_domain']);
    }
}
