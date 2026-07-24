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
    public function findByCollectionGroup(CollectionGroup $collectionGroup, ?int $limit = null): array
    {
        $qb = $this->createQueryBuilder('c')
            ->andWhere('c.collectionGroup = :collectionGroup')
            ->setParameter('collectionGroup', $collectionGroup)
            ->orderBy('c.position', 'ASC')
        ;

        if (null !== $limit) {
            $qb->setMaxResults($limit);
        }

        return $qb->getQuery()->getResult();
    }

    public function countByCollectionGroup(CollectionGroup $collectionGroup): int
    {
        return (int) $this->createQueryBuilder('c')
            ->select('COUNT(c.id)')
            ->andWhere('c.collectionGroup = :collectionGroup')
            ->setParameter('collectionGroup', $collectionGroup)
            ->getQuery()
            ->getSingleScalarResult()
        ;
    }

    // Used both to enforce the collection-scoped slug uniqueness (see CollectionItemCrudController) and to resolve a "collection" block item's detail view (see CollectionItemSourceProvider's "detail" key)
    public function findOneByCollectionGroupAndSlug(CollectionGroup $collectionGroup, string $slug): ?CollectionItem
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.collectionGroup = :collectionGroup')
            ->andWhere('c.slug = :slug')
            ->setParameter('collectionGroup', $collectionGroup)
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
