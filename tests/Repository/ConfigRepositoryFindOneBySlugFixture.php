<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Repository;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Repository\ConfigRepository;

// findOneBySlug() is resolved by Doctrine's EntityRepository::__call() magic (no real declared method to mock), so callers needing a double override it directly here instead - mirrors ConfigBundle's own ConfigRepositoryFindOneBySlugFixture (not reusable across packages, ConfigBundle's tests/ isn't part of its published autoload). The parent constructor is never invoked, which is safe since this fixture never touches the (otherwise uninitialized) Doctrine internals.
class ConfigRepositoryFindOneBySlugFixture extends ConfigRepository
{
    private ?string $requestedSlug = null;

    public function __construct(private readonly ?Config $config)
    {
    }

    public function findOneBySlug(string $slug): ?Config
    {
        $this->requestedSlug = $slug;

        return $this->config;
    }

    public function getRequestedSlug(): ?string
    {
        return $this->requestedSlug;
    }
}
