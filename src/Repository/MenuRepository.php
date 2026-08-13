<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Repository;

use c975L\SiteBundle\Entity\Menu;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Menu>
 */
class MenuRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Menu::class);
    }

    // Eager-joins blocks so MenuExtension::getMenuBlocks() doesn't trigger a second query for the lazy-loaded ManyToMany collection (see PageRepository's own findOneBySlugForDisplay(), same pattern) - ordering comes from Menu::$blocks' own #[ORM\OrderBy], applied automatically to the joined collection
    // Their slots and their medias are joined for a second reason on top of that one: MenuExtension caches these very entities across requests, and a lazy collection left uninitialized comes back from that cache detached and empty - a container block (a "menu_group" holding a footer's links) would render nothing at all, and any kind carrying a media (a menu is not restricted to "menu_link", see MenuCrudController's own context) would lose its image on the render following its edit. Medias are taken at both levels, the slots' as well as the top-level blocks' - PageRepository joins its own the same way
    public function findOneByLocation(string $location): ?Menu
    {
        return $this->createQueryBuilder('m')
            ->select('m, b, s, bm, sm')
            ->leftJoin('m.blocks', 'b')
            ->leftJoin('b.slots', 's')
            ->leftJoin('b.medias', 'bm')
            ->leftJoin('s.medias', 'sm')
            ->andWhere('m.location = :location')
            ->setParameter('location', $location)
            ->getQuery()
            ->getOneOrNullResult()
        ;
    }

    // The menus owning any of the given Block rows - what MenuBlockEditUrlProvider points the front-end "Edit" hover button at, PageRepository::findByBlockIds() being its Page counterpart. Only the matched blocks are hydrated into each menu's collection, which is all the caller reads
    public function findByBlockIds(array $blockIds): array
    {
        if ([] === $blockIds) {
            return [];
        }

        return $this->createQueryBuilder('m')
            ->select('m, b')
            ->innerJoin('m.blocks', 'b')
            ->andWhere('b.id IN (:blockIds)')
            ->setParameter('blockIds', $blockIds)
            ->getQuery()
            ->getResult()
        ;
    }
}
