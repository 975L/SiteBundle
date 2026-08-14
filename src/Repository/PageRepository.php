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

    // Find the first published page carrying a "form" Block pointing at the given Form name (e.g. "register"/"reset_password_request", picked by name in the admin same as "contact") - used to link a generic/bare route's own cross-references (login form's "forgot password"/"create account") to the real, translated-slug Page instead. Block::$data is JSON, so matching on its "name" key is done in PHP after narrowing to "form"-kind blocks (few per site)
    public function findOneByFormBlockName(string $formName): ?Page
    {
        $pages = $this->createQueryBuilder('p')
            ->select('p, b')
            ->innerJoin('p.blocks', 'b')
            ->andWhere('b.kind = :kind')
            ->andWhere('p.isPublished = :published')
            ->andWhere('p.isDeleted = :deleted')
            ->setParameter('kind', 'form')
            ->setParameter('published', true)
            ->setParameter('deleted', false)
            ->getQuery()
            ->getResult()
        ;

        foreach ($pages as $page) {
            foreach ($page->getBlocks() as $block) {
                if ('form' === $block->getKind() && $formName === ($block->getData()['name'] ?? null)) {
                    return $page;
                }
            }
        }

        return null;
    }

    // Find pages owning any of the given blocks (used by SiteMediaUsageProvider to resolve a Block's owning Page)
    public function findByBlockIds(array $blockIds): array
    {
        if ([] === $blockIds) {
            return [];
        }

        return $this->createQueryBuilder('p')
            ->select('p, b')
            ->innerJoin('p.blocks', 'b')
            ->andWhere('b.id IN (:blockIds)')
            ->setParameter('blockIds', $blockIds)
            ->getQuery()
            ->getResult()
        ;
    }

    // Find published pages having a legal_model block matching one of the given model identifiers (e.g. 'france/cookies'), preserving the given order
    public function findByLegalModels(array $models): array
    {
        return $this->findByLegalModelsWithStatus($models, true);
    }

    // Same, whatever the page's status - what tells "no page carries this model at all" from "the page exists but was unpublished or trashed", the two calling for a different alert
    public function findByLegalModelsAnyStatus(array $models): array
    {
        return $this->findByLegalModelsWithStatus($models, false);
    }

    // Only the three scalars the dashboard alert reads: hydrating the whole home page with its blocks and medias to look at two booleans is the cost of a full render
    public function findHomePublicationStatus(string $slug): ?array
    {
        return $this->createQueryBuilder('p')
            ->select('p.id, p.isPublished, p.isDeleted')
            ->andWhere('p.slug = :slug')
            ->setParameter('slug', $slug)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    private function findByLegalModelsWithStatus(array $models, bool $publishedOnly): array
    {
        $queryBuilder = $this->createQueryBuilder('p')
            ->select('p, b')
            ->innerJoin('p.blocks', 'b')
            ->andWhere('b.kind = :kind')
            ->setParameter('kind', 'legal_model')
        ;

        if ($publishedOnly) {
            $queryBuilder
                ->andWhere('p.isPublished = :published')
                ->andWhere('p.isDeleted = :deleted')
                ->setParameter('published', true)
                ->setParameter('deleted', false)
            ;
        }

        $pages = $queryBuilder->getQuery()->getResult();

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
