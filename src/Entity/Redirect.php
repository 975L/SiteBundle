<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Entity;

use c975L\SiteBundle\Repository\RedirectRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Bridge\Doctrine\Validator\Constraints\UniqueEntity;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RedirectRepository::class)]
#[ORM\Table(name: 'site_redirect')]
#[UniqueEntity('fromPath')]
class Redirect
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255, unique: true)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $fromPath = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $toUrl = null;

    #[ORM\Column(type: Types::BOOLEAN, options: ['default' => true])]
    private bool $permanent = true;

    public function __toString(): string
    {
        return $this->fromPath ?? '';
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getFromPath(): ?string
    {
        return $this->fromPath;
    }

    public function setFromPath(string $fromPath): self
    {
        $this->fromPath = '/' . ltrim($fromPath, '/');

        return $this;
    }

    public function getToUrl(): ?string
    {
        return $this->toUrl;
    }

    public function setToUrl(string $toUrl): self
    {
        $this->toUrl = $toUrl;

        return $this;
    }

    public function isPermanent(): bool
    {
        return $this->permanent;
    }

    public function setPermanent(bool $permanent): self
    {
        $this->permanent = $permanent;

        return $this;
    }
}
