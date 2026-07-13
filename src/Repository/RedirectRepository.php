<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Repository;

use c975L\SiteBundle\Entity\Redirect;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class RedirectRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Redirect::class);
    }

    public function findOneByFromPath(string $fromPath): ?Redirect
    {
        return $this->findOneBy(['fromPath' => $fromPath]);
    }

    /**
     * @return Redirect[]
     */
    public function findByToUrl(string $toUrl): array
    {
        return $this->findBy(['toUrl' => $toUrl]);
    }
}
