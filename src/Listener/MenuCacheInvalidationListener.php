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

// A Menu's own row is never touched by adding/removing/reordering/editing one of its blocks (that ManyToMany's join table isn't Menu's own mapped state, see MenuRepository::findOneByLocation()) - but every one of those actions is a lifecycle event on the Block itself instead: adding one persists a new Block (cascade, see Menu::$blocks), removing one is a cascade-remove (see BlockRemovalListener), and reordering/editing updates its position/data column. Listening on Block here is what catches all of them for MenuExtension::loadMenuBlocks()'s own cache
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
class MenuCacheInvalidationListener extends AbstractBlockCacheInvalidationListener
{
    public function __construct(
        private readonly TagAwareCacheInterface $cache,
    ) {
    }

    // The Menu row is watched for its own layout style (see Menu::$style), which no Block event signals. Every Block kind is watched, not "menu_link"/"menu_group" alone: a footer and the "navbar-brand" menu take any kind and a Block does not know its owner, so a removed tagline stayed cached for good - a page block saved only costs the menus one lookup on the next request
    protected function invalidate(object $entity): void
    {
        if ($entity instanceof Menu || $entity instanceof Block) {
            $this->cache->invalidateTags(['menus_all']);
        }
    }
}
