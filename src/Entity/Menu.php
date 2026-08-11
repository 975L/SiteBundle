<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Entity;

use c975L\SiteBundle\Repository\MenuRepository;
use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Trait\HasBlocksTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: MenuRepository::class)]
#[ORM\Table(name: 'site_menu')]
class Menu implements HasBlocksInterface, \Stringable
{
    use HasBlocksTrait;

    // Site-wide menus, one row per location - enforced at the DB level (see $location). Each owns a single ordered $blocks collection: menu links are the "menu_link" Block kind (see MenuLinkType/MenuExtension::getMenuLinkUrl()), sortable alongside any other block
    public const LOCATION_NAVBAR = 'navbar';
    public const LOCATION_FOOTER = 'footer';
    public const LOCATION_EMAIL_FOOTER = 'email-footer';
    public const LOCATION_EMAIL_HEADER = 'email-header';

    // How the menu's own items are laid out, null meaning "whatever the site's theme decided" (the --footer-items-* tokens) - only the footer reads it so far, see Footer.html.twig
    public const STYLE_INLINE = 'inline';
    public const STYLE_BLOCK = 'block';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 20, unique: true)]
    private ?string $location = null;

    #[ORM\Column(length: 20, nullable: true)]
    private ?string $style = null;

    #[ORM\ManyToMany(targetEntity: Block::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'site_menu_blocks')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $blocks;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->location ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getLocation(): ?string
    {
        return $this->location;
    }

    public function setLocation(?string $location): self
    {
        $this->location = $location;

        return $this;
    }

    public function getStyle(): ?string
    {
        return $this->style;
    }

    // Anything but one of the STYLE_* constants is stored as null: the value ends up in a CSS class, and an unknown one would only ever name a rule no stylesheet carries
    public function setStyle(?string $style): self
    {
        $this->style = in_array($style, [self::STYLE_INLINE, self::STYLE_BLOCK], true) ? $style : null;

        return $this;
    }
}
