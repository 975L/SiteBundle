<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Entity;

use c975L\SiteBundle\Repository\MenuItemRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

// Exactly one of $page / $route must be set - $page targets an existing published Page (see Readme),
// $route targets a bundle-contributed route (see LinkableRouteProviderInterface, also Readme)
#[ORM\Entity(repositoryClass: MenuItemRepository::class)]
#[ORM\Table(name: 'site_menu_item')]
#[Assert\Expression(
    expression: '(this.getPage() === null) != (this.getRoute() === null)',
    message: 'constraint.menu_item_target'
)]
class MenuItem
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Menu::class, inversedBy: 'items')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private ?Menu $menu = null;

    // The relation is kept on the page's id (stable even if its slug changes) - the front-end
    // components resolve the actual URL from the page's slug at render time (see MenuItem.html.twig)
    #[ORM\ManyToOne(targetEntity: Page::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'CASCADE')]
    private ?Page $page = null;

    // A route name contributed by a LinkableRouteProviderInterface (e.g. "contactform_display") - not a
    // Page, used for bundle-provided front routes that don't go through the Page CRUD
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $route = null;

    #[ORM\Column(nullable: true)]
    private int $position = 0;

    public function __toString(): string
    {
        return '(#' . $this->position . ') ' . ($this->page?->getTitle() ?? $this->route ?? '');
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getMenu(): ?Menu
    {
        return $this->menu;
    }

    public function setMenu(?Menu $menu): self
    {
        $this->menu = $menu;

        return $this;
    }

    public function getPage(): ?Page
    {
        return $this->page;
    }

    public function setPage(?Page $page): self
    {
        $this->page = $page;

        return $this;
    }

    public function getRoute(): ?string
    {
        return $this->route;
    }

    public function setRoute(?string $route): self
    {
        $this->route = $route;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(?int $position): self
    {
        $this->position = $position ?? 0;

        return $this;
    }
}
