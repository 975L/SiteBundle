<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Entity;

use c975L\SiteBundle\Repository\CollectionGroupRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

// Named, slugified grouping of CollectionItems (e.g. "Projects") - its slug is what CollectionItemSourceProvider exposes as a "site.collection.{slug}" source, pickable in a page's "Collection" block. Created explicitly here via CollectionCrudController (rather than free-typed on the item itself, as before) so a typo can no longer silently spawn a brand-new, unrelated collection - see CollectionItemCrudController, scoped to one CollectionGroup at a time.
#[ORM\Entity(repositoryClass: CollectionGroupRepository::class)]
#[ORM\Table(name: 'site_collection_group')]
#[UniqueEntity(fields: ['name'])]
#[UniqueEntity(fields: ['slug'])]
class CollectionGroup
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 150, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private ?string $name = null;

    #[ORM\Column(length: 150, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private ?string $slug = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getName(): ?string
    {
        return $this->name;
    }

    public function setName(?string $name): self
    {
        $this->name = $name;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(?string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function __toString(): string
    {
        return $this->name ?? '';
    }
}
