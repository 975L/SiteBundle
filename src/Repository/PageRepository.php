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
            ->andWhere('p.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->setParameter('published', true)
            ->orderBy('p.slug', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    // Find one page by id, with its blocks and their medias eager-loaded (used by the articles_slider block)
    public function findOneByIdWithBlocks(int $id): ?Page
    {
        return $this->createQueryBuilder('p')
            ->select('p, b, m')
            ->leftJoin('p.blocks', 'b')
            ->leftJoin('b.medias', 'm')
            ->andWhere('p.id = :id')
            ->setParameter('id', $id)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    // Find one page by slug (published only)
    public function findOneBySlug(string $slug): ?Page
    {
        return $this->createQueryBuilder('p')
            ->select('p, b, m')
            ->leftJoin('p.blocks', 'b')
            ->leftJoin('b.medias', 'm')
            ->andWhere('p.slug = :slug')
            ->andWhere('p.isPublished = :published')
            ->andWhere('p.isDeleted = :deleted')
            ->setParameter('published', true)
            ->setParameter('deleted', false)
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    // Find one page by slug regardless of status (for display: handles redirects and 410)
    public function findOneBySlugForDisplay(string $slug): ?Page
    {
        return $this->createQueryBuilder('p')
            ->select('p, b, m')
            ->leftJoin('p.blocks', 'b')
            ->leftJoin('b.medias', 'm')
            ->andWhere('p.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    // Find published pages having a legal_model block matching one of the given model identifiers (e.g. 'france/cookies'), preserving the given order
    public function findByLegalModels(array $models): array
    {
        $pages = $this->createQueryBuilder('p')
            ->select('p, b')
            ->innerJoin('p.blocks', 'b')
            ->andWhere('b.kind = :kind')
            ->andWhere('p.isPublished = :published')
            ->andWhere('p.isDeleted = :deleted')
            ->setParameter('kind', 'legal_model')
            ->setParameter('published', true)
            ->setParameter('deleted', false)
            ->getQuery()
            ->getResult()
        ;

        $byModel = [];
        foreach ($pages as $page) {
            foreach ($page->getBlocks() as $block) {
                if ('legal_model' === $block->getKind()) {
                    $byModel[$block->getData()['model'] ?? ''] = $page;
                }
            }
        }

        $result = [];
        foreach ($models as $model) {
            if (isset($byModel[$model])) {
                $result[] = $byModel[$model];
            }
        }

        return $result;
    }
}
