<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Repository;

use c975L\SiteBundle\Entity\CollectionGroup;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CollectionGroup>
 */
class CollectionGroupRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CollectionGroup::class);
    }

    public function findOneBySlug(string $slug): ?CollectionGroup
    {
        return $this->findOneBy(['slug' => $slug]);
    }

    // Lets a caller (CollectionItemImportProvider, CollectionItemImportCommand) resolve an existing collection by its
    // exact name, or create a fresh one when it doesn't exist yet on this environment
    public function findOneByName(string $name): ?CollectionGroup
    {
        return $this->findOneBy(['name' => $name]);
    }
}
