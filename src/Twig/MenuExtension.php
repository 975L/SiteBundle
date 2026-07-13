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

    // Resolves a "menu_link" block's raw target ("page:ID" or "route:NAME", see MenuLinkType) into an
    // actual URL - empty string if it no longer resolves (page unpublished/deleted, route no longer
    // registered by a LinkableRouteProviderInterface), so the template can skip rendering it
    public function getMenuLinkUrl(string $target): string
    {
        [$type, $value] = array_pad(explode(':', $target, 2), 2, null);

        if ('page' === $type) {
            $page = $this->pageRepository->find($value);

            return null === $page || !$page->isPublished() || $page->isDeleted()
                ? ''
                : $this->router->generate('page_display', ['page' => $page->getSlug()]);
        }

        return 'route' === $type && null !== $value && $this->linkableRouteRegistry->has($value)
            ? $this->router->generate($value)
            : '';
    }

    public function getMenuLinkLabel(string $target): string
    {
        [$type, $value] = array_pad(explode(':', $target, 2), 2, null);

        if ('page' === $type) {
            $page = $this->pageRepository->find($value);

            return null === $page ? '' : (string) $page->getTitle();
        }

        if ('route' === $type && null !== $value) {
            $route = $this->linkableRouteRegistry->get($value);

            return null === $route ? '' : $this->translator->trans($route['label'], [], $route['translation_domain']);
        }

        return '';
    }
}
