<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Repository;

use c975L\SiteBundle\Entity\CollectionItem;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<CollectionItem>
 */
class CollectionItemRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, CollectionItem::class);
    }

    // @return CollectionItem[]
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

    // Used both to enforce the group-scoped slug uniqueness (see CollectionItemCrudController) and to resolve a "collection" block item's detail view (see CollectionItemSourceProvider's "detail" key)
    public function findOneByGroupAndSlug(string $group, string $slug): ?CollectionItem
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.group = :group')
            ->andWhere('c.slug = :slug')
            ->setParameter('group', $group)
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    // Every distinct group currently in use - CollectionItemSourceProvider exposes one CollectionSourceProviderInterface source per group found here
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

    // Item count per group, ordered by group - backs CollectionItemCrudController's intermediate "pick a group" index screen
    public function countsByGroup(): array
    {
        $rows = $this->createQueryBuilder('c')
            ->select('c.group AS grp, COUNT(c.id) AS itemCount')
            ->groupBy('c.group')
            ->orderBy('c.group', 'ASC')
            ->getQuery()
            ->getResult()
        ;

        return array_combine(array_column($rows, 'grp'), array_column($rows, 'itemCount'));
    }
}
