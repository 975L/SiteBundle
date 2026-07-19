<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Repository;

use c975L\SiteBundle\Entity\Menu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Menu::class);
    }

    // Eager-joins blocks so MenuExtension::getMenuBlocks() doesn't trigger a second query for the lazy-loaded ManyToMany collection (see PageRepository's own findOneBySlugForDisplay(), same pattern) - ordering comes from Menu::$blocks' own #[ORM\OrderBy], applied automatically to the joined collection
    public function findOneByLocation(string $location): ?Menu
    {
        return $this->createQueryBuilder('m')
            ->select('m, b')
            ->leftJoin('m.blocks', 'b')
            ->andWhere('m.location = :location')
            ->setParameter('location', $location)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
