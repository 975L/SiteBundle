<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Entity;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Contract\HasBlocksInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Entity\Trait\HasBlocksTrait;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PageRepository::class)]
#[ORM\Table(name: 'site_page')]
#[ORM\HasLifecycleCallbacks]
#[UniqueEntity('slug')]
class Page implements HasBlocksInterface, \Stringable
{
    use HasBlocksTrait;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $title = null;

    #[ORM\Column(length: 100, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 100)]
    private ?string $slug = null;

    #[ORM\Column(type: Types::TEXT, nullable: true)]
    private ?string $summarySocialNetwork = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $creation = null;

    #[ORM\Column(type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $modification = null;

    #[ORM\ManyToOne]
    private ?UserInterface $user = null;

    // Whether the page is meant to be referenced by search engines. True by default - opting a page out is the exception, meant for pages with no SEO value (e.g. "creer-un-compte", "mot-de-passe-oublie", created by DefaultPagesImporter). Drives both its presence in sitemap-site.xml (see SitePageSitemapProvider::getUrls()) and its "robots" meta tag (see layout.html.twig): excluding a page from the sitemap alone wouldn't prevent indexing, since the sitemap is only a crawl hint
    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $isIndexable = true;

    // Benign per-page display options, stored as one JSON payload rather than a column each - same reasoning as UiBundle's Block::$data: a new option is then a code change alone, no schema migration for every app running this bundle to replay. Read/written through the named accessors below, never as raw string keys: they hold each option's default in one place. Anything the database itself has to filter, sort or join on (slug, isPublished, isIndexable...) stays a real column
    #[ORM\Column(type: Types::JSON, nullable: true)]
    private ?array $options = null;

    #[ORM\Column(type: Types::INTEGER, nullable: true)]
    #[Assert\Range(min: 0, max: 10)]
    private ?int $priority = null;

    #[ORM\Column(length: 50, nullable: true)]
    #[Assert\Length(max: 50)]
    private ?string $changeFrequency = null;

    #[ORM\ManyToMany(targetEntity: Block::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinTable(name: 'site_page_blocks')]
    #[ORM\OrderBy(['position' => 'ASC'])]
    private Collection $blocks;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isPublished = false;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => false])]
    private bool $isDeleted = false;

    // Id of the page this one is meant to take over once approved (see PageCrudController::publishAsReplacement()) - an id, not the slug, so the lookup stays correct even if that page's slug changes (e.g. archived by another draft's own publishAsReplacement()) in the meantime. Null once published as a replacement, or for any page not created that way.
    #[ORM\Column(nullable: true)]
    private ?int $replaces = null;

    // The real slug this page held before publishAsReplacement() archived it under a technical one - lets restore() try to reclaim it. Null once restored (or reclaim failed), or for any page never archived this way.
    #[ORM\Column(length: 100, nullable: true)]
    private ?string $archivedSlug = null;

    // Falls back to the site-wide default og-image (see MediaExtension::getSiteMedia) when not set. cascade: remove - this Media is exclusively owned by this Page (not shared), so deleting the Page must also delete its file, same as Block's own medias
    #[ORM\ManyToOne(targetEntity: Media::class, cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?Media $ogImage = null;

    public function __construct()
    {
        $this->blocks = new ArrayCollection();
    }

    public function __toString(): string
    {
        return $this->title ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getTitle(): ?string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getSlug(): ?string
    {
        return $this->slug;
    }

    public function setSlug(string $slug): self
    {
        $this->slug = $slug;

        return $this;
    }

    public function getSummarySocialNetwork(): ?string
    {
        return $this->summarySocialNetwork;
    }

    public function setSummarySocialNetwork(?string $summarySocialNetwork): self
    {
        $this->summarySocialNetwork = $summarySocialNetwork;

        return $this;
    }

    public function getCreation(): ?\DateTimeInterface
    {
        return $this->creation;
    }

    public function setCreation(\DateTimeInterface $creation): self
    {
        $this->creation = $creation;

        return $this;
    }

    public function getModification(): ?\DateTimeInterface
    {
        return $this->modification;
    }

    public function setModification(\DateTimeInterface $modification): self
    {
        $this->modification = $modification;

        return $this;
    }

    public function getUser(): ?UserInterface
    {
        return $this->user;
    }

    public function setUser(?UserInterface $user): static
    {
        $this->user = $user;

        return $this;
    }

    // An unpublished page is never referenced either: whatever unpublished it (the edit form, the trash, publishAsReplacement(), a duplication, an import) and in whatever order its setters were called, isIndexable follows isPublished down. Republishing never puts it back - referencing a page again is a deliberate call, made by re-checking the field. Enforced here rather than in setIsPublished(), which the edit form calls before setIsIndexable() and would let the submitted "true" win right after. PreFlush and not PreUpdate: the changeset is computed after this runs, where a PreUpdate callback's own writes come too late to be persisted
    #[ORM\PreFlush]
    public function unreferenceWhenUnpublished(): void
    {
        if (!$this->isPublished) {
            $this->isIndexable = false;
        }
    }

    public function isIndexable(): bool
    {
        return $this->isIndexable;
    }

    public function setIsIndexable(bool $isIndexable): static
    {
        $this->isIndexable = $isIndexable;

        return $this;
    }

    public function getOptions(): array
    {
        return $this->options ?? [];
    }

    public function setOptions(array $options): static
    {
        $this->options = $options;

        return $this;
    }

    // $default is what an option means when it was never set - every page predating it, and every one whose editor left it alone
    public function getOption(string $key, mixed $default = null): mixed
    {
        return $this->getOptions()[$key] ?? $default;
    }

    public function setOption(string $key, mixed $value): static
    {
        $options = $this->getOptions();
        $options[$key] = $value;

        return $this->setOptions($options);
    }

    // Whether the layout prints the page's own title as its <h1> (see layout.html.twig). True by default - hiding it is for a page opened by a block carrying its own <h1> (a "hero"/"banner_title" left on its h1 level), where printing it too would give the page two top-level headings. A page ending up with none is reported by the content-quality health check, same as one with several
    public function isTitleDisplayed(): bool
    {
        return (bool) $this->getOption('titleDisplayed', true);
    }

    public function setIsTitleDisplayed(bool $isTitleDisplayed): static
    {
        return $this->setOption('titleDisplayed', $isTitleDisplayed);
    }

    public function getPriority(): ?int
    {
        return $this->priority;
    }

    public function setPriority(?int $priority): static
    {
        $this->priority = $priority;

        return $this;
    }

    public function getChangeFrequency(): ?string
    {
        return $this->changeFrequency;
    }

    public function setChangeFrequency(?string $changeFrequency): static
    {
        $this->changeFrequency = $changeFrequency;

        return $this;
    }

    public function isPublished(): bool
    {
        return $this->isPublished;
    }

    public function setIsPublished(bool $isPublished): self
    {
        $this->isPublished = $isPublished;

        return $this;
    }

    public function isDeleted(): bool
    {
        return $this->isDeleted;
    }

    public function setIsDeleted(bool $isDeleted): self
    {
        $this->isDeleted = $isDeleted;

        return $this;
    }

    public function getReplaces(): ?int
    {
        return $this->replaces;
    }

    public function setReplaces(?int $replaces): self
    {
        $this->replaces = $replaces;

        return $this;
    }

    public function getArchivedSlug(): ?string
    {
        return $this->archivedSlug;
    }

    public function setArchivedSlug(?string $archivedSlug): self
    {
        $this->archivedSlug = $archivedSlug;

        return $this;
    }

    public function getOgImage(): ?Media
    {
        return $this->ogImage;
    }

    public function setOgImage(?Media $ogImage): self
    {
        $this->ogImage = $ogImage;

        return $this;
    }
}
