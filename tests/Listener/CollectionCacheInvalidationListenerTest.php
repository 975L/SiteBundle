<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Listener;

use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Listener\CollectionCacheInvalidationListener;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use c975L\SiteBundle\Service\CollectionItemTranslator;
use c975L\UiBundle\Entity\Translation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class CollectionCacheInvalidationListenerTest extends TestCase
{
    private function createCollectionGroup(int $id): CollectionGroup
    {
        $collectionGroup = new CollectionGroup();
        new \ReflectionProperty(CollectionGroup::class, 'id')->setValue($collectionGroup, $id);

        return $collectionGroup;
    }

    private function createEntityManager(): EntityManagerInterface
    {
        return $this->createStub(EntityManagerInterface::class);
    }

    // The tag CollectionItemSourceProvider declares to UiBundle: editing an item is what makes every "collection" block showing it, and every one of its cached items, go stale
    public function testPostUpdateInvalidatesTheTagOfTheItemsOwnCollection(): void
    {
        $item = new CollectionItem()->setCollectionGroup($this->createCollectionGroup(4));

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['site_collection_4']);

        new CollectionCacheInvalidationListener($cache, $this->createStub(CollectionItemRepository::class))
            ->postUpdate(new PostUpdateEventArgs($item, $this->createEntityManager()));
    }

    // Adding an item to a collection is an INSERT: postUpdate never fires for it, so without this the listings would keep excluding it
    public function testPostPersistInvalidatesTheTagForABrandNewItem(): void
    {
        $item = new CollectionItem()->setCollectionGroup($this->createCollectionGroup(7));

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['site_collection_7']);

        new CollectionCacheInvalidationListener($cache, $this->createStub(CollectionItemRepository::class))
            ->postPersist(new PostPersistEventArgs($item, $this->createEntityManager()));
    }

    public function testPreRemoveInvalidatesTheTagOfTheRemovedItemsCollection(): void
    {
        $item = new CollectionItem()->setCollectionGroup($this->createCollectionGroup(9));

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['site_collection_9']);

        new CollectionCacheInvalidationListener($cache, $this->createStub(CollectionItemRepository::class))
            ->preRemove(new PreRemoveEventArgs($item, $this->createEntityManager()));
    }

    // The group's own row carries the source's label, so renaming it changes what every listing built from it says
    public function testTheCollectionGroupItselfIsWatchedToo(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['site_collection_11']);

        new CollectionCacheInvalidationListener($cache, $this->createStub(CollectionItemRepository::class))
            ->postUpdate(new PostUpdateEventArgs($this->createCollectionGroup(11), $this->createEntityManager()));
    }

    // A language screen writes the item's translation and nothing of the item itself: the collection it belongs to is what goes stale all the same
    public function testTranslatingAnItemInvalidatesItsCollection(): void
    {
        $item = new CollectionItem()->setCollectionGroup($this->createCollectionGroup(5));

        $repository = $this->createStub(CollectionItemRepository::class);
        $repository->method('find')->willReturn($item);

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['site_collection_5']);

        new CollectionCacheInvalidationListener($cache, $repository)
            ->postPersist(new PostPersistEventArgs(new Translation(CollectionItemTranslator::OWNER, 3, 'title', 'en'), $this->createEntityManager()));
    }

    public function testNothingIsInvalidatedForAnotherEntity(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('invalidateTags');

        new CollectionCacheInvalidationListener($cache, $this->createStub(CollectionItemRepository::class))
            ->postUpdate(new PostUpdateEventArgs(new \stdClass(), $this->createEntityManager()));
    }

    // An item still to be attached to a collection has no tag to be reached by
    public function testNothingIsInvalidatedForAnItemWithNoCollection(): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('invalidateTags');

        new CollectionCacheInvalidationListener($cache, $this->createStub(CollectionItemRepository::class))
            ->postUpdate(new PostUpdateEventArgs(new CollectionItem(), $this->createEntityManager()));
    }
}
