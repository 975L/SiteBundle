<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Entity;

use App\Entity\User;
use c975L\SiteBundle\Repository\CollectionEntryRepository;
use c975L\UiBundle\Contract\VichImageResizableInterface;
use c975L\UiBundle\Contract\VichMediaNamableInterface;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Validator\Constraints as Assert;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

// A single, ready-made "title/description/image/link" item, grouped by an arbitrary "group" key so one
// table can back several unrelated collections (e.g. "projects" on one site, something else on another) -
// exposed to the "collection" block via CollectionEntrySourceProvider. Its own Vich field (rather than a
// link to UiBundle\Media) reuses the same UiMediaNamer/NestedFileSystemStorage resizing/webp pipeline
// without depending on UiBundle's Media table, following BookBundle/ShopBundle's own precedent of each
// domain owning its own uploadable field.
#[ORM\Entity(repositoryClass: CollectionEntryRepository::class)]
#[ORM\Table(name: 'site_collection_entry')]
#[Vich\Uploadable]
class CollectionEntry implements VichImageResizableInterface, VichMediaNamableInterface
{
    private const IMAGE_WIDTH = 800;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $group = null;

    #[ORM\Column(length: 150)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 150)]
    private ?string $title = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $description = null;

    #[ORM\Column(length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $url = null;

    #[ORM\Column]
    private int $position = 0;

    #[Vich\UploadableField(
        mapping: 'collection_entry',
        fileNameProperty: 'filename',
        size: 'size',
        mimeType: 'mimeType'
    )]
    private ?File $file = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $filename = null;

    #[ORM\Column(nullable: true)]
    private ?int $size = null;

    #[ORM\Column(length: 100, nullable: true)]
    private ?string $mimeType = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: true)]
    private ?User $user = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getGroup(): ?string
    {
        return $this->group;
    }

    public function setGroup(?string $group): self
    {
        $this->group = $group;

        return $this;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(?string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): self
    {
        $this->description = $description;

        return $this;
    }

    public function getUrl(): ?string
    {
        return $this->url;
    }

    public function setUrl(?string $url): self
    {
        $this->url = $url;

        return $this;
    }

    public function getPosition(): int
    {
        return $this->position;
    }

    public function setPosition(int $position): self
    {
        $this->position = $position;

        return $this;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    // Vich's own upload listener triggers on this being set - see UiMediaNamer/VichImageResizeListener
    public function setFile(?File $file): self
    {
        $this->file = $file;

        if (null !== $file) {
            $this->updatedAt = new \DateTimeImmutable();
        }

        return $this;
    }

    public function getFilename(): ?string
    {
        return $this->filename;
    }

    public function setFilename(?string $filename): self
    {
        $this->filename = $filename;

        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): self
    {
        $this->size = $size;

        return $this;
    }

    public function getMimeType(): ?string
    {
        return $this->mimeType;
    }

    public function setMimeType(?string $mimeType): self
    {
        $this->mimeType = $mimeType;

        return $this;
    }

    public function getUser(): ?User
    {
        return $this->user;
    }

    public function setUser(?User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getImageWidth(): int
    {
        return self::IMAGE_WIDTH;
    }

    // Unique, non-role name in its own group's subdirectory - id is still null on first upload
    // (Vich's prePersist listener runs before the auto-increment id is assigned), same fallback as
    // UiBundle\Media::getVichMediaPath()
    public function getVichMediaPath(): string
    {
        return 'medias/site/collection-' . $this->group . '-' . ($this->id ?? uniqid());
    }

    public function __toString(): string
    {
        return $this->title ?? '';
    }
}
