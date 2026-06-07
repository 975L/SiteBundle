<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Repository;

use c975L\SiteBundle\Entity\Article;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Article>
 */
class ArticleRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Article::class);
    }

    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.isPublished = :published')
            ->setParameter('published', true)
            ->orderBy('a.position', 'ASC')
            ->addOrderBy('a.creation', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function findByPage(int $pageId): array
    {
        return $this->createQueryBuilder('a')
            ->andWhere('a.page = :pageId')
            ->andWhere('a.isPublished = :published')
            ->setParameter('pageId', $pageId)
            ->setParameter('published', true)
            ->orderBy('a.position', 'ASC')
            ->addOrderBy('a.creation', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
