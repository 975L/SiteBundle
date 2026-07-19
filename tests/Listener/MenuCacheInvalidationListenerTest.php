<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Listener;

use c975L\SiteBundle\Listener\MenuCacheInvalidationListener;
use c975L\UiBundle\Entity\Block;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class MenuCacheInvalidationListenerTest extends TestCase
{
    // Adding a new menu item persists a brand new "menu_link" Block (cascade, see Menu::$blocks) - postPersist is the only event that fires for it
    public function testPostPersistInvalidatesMenusAllTagForANewMenuLinkBlock(): void
    {
        $block = (new Block())->setKind('menu_link');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['menus_all']);

        (new MenuCacheInvalidationListener($cache))
            ->postPersist(new PostPersistEventArgs($block, $this->createStub(EntityManagerInterface::class)));
    }

    // Reordering or editing an existing menu item's target/label updates that Block's own position/data column
    public function testPostUpdateInvalidatesMenusAllTagForAnEditedMenuLinkBlock(): void
    {
        $block = (new Block())->setKind('menu_link');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['menus_all']);

        (new MenuCacheInvalidationListener($cache))
            ->postUpdate(new PostUpdateEventArgs($block, $this->createStub(EntityManagerInterface::class)));
    }

    // Removing a menu item cascade-removes its Block entity (see BlockRemovalListener), not just the join row
    public function testPreRemoveInvalidatesMenusAllTagForARemovedMenuLinkBlock(): void
    {
        $block = (new Block())->setKind('menu_link');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['menus_all']);

        (new MenuCacheInvalidationListener($cache))
            ->preRemove(new PreRemoveEventArgs($block, $this->createStub(EntityManagerInterface::class)));
    }

    // A Block of any other kind never belongs to a Menu - nothing to invalidate here
    public function testInvalidateIsSkippedForBlocksOfAnotherKind(): void
    {
        $block = (new Block())->setKind('article');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('invalidateTags');

        (new MenuCacheInvalidationListener($cache))
            ->postUpdate(new PostUpdateEventArgs($block, $this->createStub(EntityManagerInterface::class)));
    }

    public function testInvalidateIsSkippedForEntitiesThatAreNotBlocks(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('invalidateTags');

        (new MenuCacheInvalidationListener($cache))
            ->postUpdate(new PostUpdateEventArgs(new \stdClass(), $this->createStub(EntityManagerInterface::class)));
    }
}
