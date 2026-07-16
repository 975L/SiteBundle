<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Repository;

use c975L\SiteBundle\Entity\CollectionEntry;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CollectionEntry>
 */
class CollectionEntryRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CollectionEntry::class);
    }

    // @return CollectionEntry[]
    public function findByGroup(string $group, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.group = :group')
            ->setParameter('group', $group)
            ->orderBy('c.position', 'ASC')
        ;

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByGroup(string $group): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.group = :group')
            ->setParameter('group', $group)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    // Every distinct group currently in use - CollectionEntrySourceProvider exposes one
    // CollectionSourceProviderInterface source per group found here
    public function findDistinctGroups(): array
    {
        return array_column(
            $this->createQueryBuilder('c')
                ->select('DISTINCT c.group')
                ->getQuery()
                ->getScalarResult(),
            'group'
        );
    }
}
