<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Repository\Entity;

use c975L\ConfigBundle\Contract\UserInterface;
use Doctrine\ORM\Mapping as ORM;

// The user a Page names its author by, mapped for the SQLite schema of PageRepositoryTest - the application's own User being out of reach of a bundle's tests
#[ORM\Entity]
class PageAuthorFixture implements UserInterface
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRoles(): array
    {
        return [];
    }

    public function eraseCredentials(): void
    {
    }

    public function getUserIdentifier(): string
    {
        return 'author';
    }
}
