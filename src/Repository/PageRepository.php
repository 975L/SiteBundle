<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Repository;

use c975L\SiteBundle\Entity\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Page>
 */
class PageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Page::class);
    }

    // Find all pages ordered by position
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p, b, m')
            ->leftJoin('p.blocks', 'b')
            ->leftJoin('b.medias', 'm')
            ->andWhere('p.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('p.position', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    // Find one page by slug
    public function findOneBySlug(string $slug): ?Page
    {
        return $this->createQueryBuilder('p')
            ->select('p, b, m')
            ->leftJoin('p.blocks', 'b')
            ->leftJoin('b.medias', 'm')
            ->andWhere('p.slug = :slug')
            ->andWhere('p.isPublished = :published')
            ->setParameter('published', true)
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }
}
