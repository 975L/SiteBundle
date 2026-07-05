<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Repository;

use c975L\SiteBundle\Entity\MenuItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class MenuItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, MenuItem::class);
    }

    // All items for a given location (page-based or route-based) - MenuExtension filters out page items
    // whose page is no longer published/deleted, and route items whose route is no longer registered
    public function findByLocation(string $location): array
    {
        return $this->createQueryBuilder('mi')
            ->select('mi, p')
            ->innerJoin('mi.menu', 'm')
            ->leftJoin('mi.page', 'p')
            ->andWhere('m.location = :location')
            ->setParameter('location', $location)
            ->orderBy('mi.position', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }
}
