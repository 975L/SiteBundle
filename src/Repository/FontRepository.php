<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Repository;

use c975L\SiteBundle\Entity\Font;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Font>
 */
class FontRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Font::class);
    }

    // @return Font[]
    public function findAllOrdered(): array
    {
        return $this->createQueryBuilder('f')
            ->orderBy('f.name', 'ASC')
            ->addOrderBy('f.weight', 'ASC')
            ->getQuery()
            ->getResult()
        ;
    }

    // Distinct font-family names currently uploaded, offered by FontService alongside the dev-declared ones
    public function findDistinctNames(): array
    {
        return array_column(
            $this->createQueryBuilder('f')
                ->select('DISTINCT f.name')
                ->getQuery()
                ->getScalarResult(),
            'name'
        );
    }
}
