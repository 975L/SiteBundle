<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Entity;

use c975L\SiteBundle\Repository\FontRepository;
use c975L\UiBundle\Contract\VichMediaNamableInterface;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\HttpFoundation\File\File;
use Vich\UploaderBundle\Mapping\Attribute as Vich;

// An admin-uploaded font file (ttf/woff/woff2), turned into a @font-face rule by FontCssListener (compiled to
// public/bundles/build/site-fonts-uploaded.css) - alongside the dev-declared fonts in _fonts.css (see FontService),
// this is the second source FontRegistry can offer to the "font" kind config selects. Own Vich field rather than
// UiBundle\Media, same precedent as CollectionItem: not an image, would need to skip all of Media's resize logic
#[ORM\Entity(repositoryClass: FontRepository::class)]
#[ORM\Table(name: 'site_font')]
#[Vich\Uploadable]
class Font implements VichMediaNamableInterface
{
    // Sentinel `weight` value meaning "this file is a variable font" instead of a single static cut - real weights
    // start at 100, so 0 can't collide. FontCssListener emits a fixed "100 900" @font-face range for it instead of
    // trying to read the file's actual fvar axis (unreliable to guess, and its .woff2 encoding hides it behind Brotli)
    public const WEIGHT_VARIABLE = 0;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    // The font-family name used in the generated @font-face rule and offered by FontChoiceType - several rows can share
    // the same name with a different weight/style (e.g. the regular and the bold cut of "Roboto" are two uploads)
    #[ORM\Column(length: 100)]
    private ?string $name = null;

    #[ORM\Column(options: ['default' => 400])]
    private int $weight = 400;

    #[ORM\Column(length: 10, options: ['default' => 'normal'])]
    private string $style = 'normal';

    #[Vich\UploadableField(
        mapping: 'site_font',
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

    public function getWeight(): int
    {
        return $this->weight;
    }

    public function setWeight(?int $weight): self
    {
        $this->weight = $weight ?? 400;

        return $this;
    }

    public function isVariable(): bool
    {
        return self::WEIGHT_VARIABLE === $this->weight;
    }

    public function getStyle(): string
    {
        return $this->style;
    }

    public function setStyle(?string $style): self
    {
        $this->style = $style ?? 'normal';

        return $this;
    }

    public function getFile(): ?File
    {
        return $this->file;
    }

    public function setFile(?File $file): self
    {
        $this->file = $file;

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

    // Format token expected by @font-face's src: url(...) format(...), derived from the uploaded file's own extension
    public function getFormat(): ?string
    {
        return match (strtolower(pathinfo($this->filename ?? '', PATHINFO_EXTENSION))) {
            'ttf' => 'truetype',
            'woff' => 'woff',
            'woff2' => 'woff2',
            default => null,
        };
    }

    // Unique, non-role path in public/medias/fonts - id is still null on first upload (Vich's prePersist listener
    // runs before the auto-increment id is assigned), same fallback as CollectionItem::getVichMediaPath()
    public function getVichMediaPath(): string
    {
        return 'medias/fonts/font-' . ($this->id ?? uniqid());
    }

    public function __toString(): string
    {
        $weight = $this->isVariable() ? 'variable' : (string) $this->weight;

        return trim(($this->name ?? '') . ' ' . $weight . ' ' . $this->style);
    }
}
