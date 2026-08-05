<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Listener;

use c975L\SiteBundle\Entity\Menu;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Listener\AbstractBlockCacheInvalidationListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// A Menu's own row is never touched by adding/removing/reordering/editing one of its "menu_link" items (that ManyToMany's join table isn't Menu's own mapped state, see MenuRepository::findOneByLocation()) - but every one of those actions is a lifecycle event on the Block itself instead: adding one persists a new Block (cascade, see Menu::$blocks), removing one is a cascade-remove (see BlockRemovalListener), and reordering/editing updates its position/data column. Listening on Block here, filtered to "menu_link", is what catches all of them for MenuExtension::loadMenuBlocks()'s own cache
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
class MenuCacheInvalidationListener extends AbstractBlockCacheInvalidationListener
{
    public function __construct(
        private readonly TagAwareCacheInterface $cache,
    ) {
    }

    // The Menu row itself is watched too since it carries a field of its own: its layout style (see Menu::$style, read by MenuExtension::getMenuStyle()), which nothing on the Block side would ever signal
    protected function invalidate(object $entity): void
    {
        if ($entity instanceof Menu || ($entity instanceof Block && 'menu_link' === $entity->getKind())) {
            $this->cache->invalidateTags(['menus_all']);
        }
    }
}
