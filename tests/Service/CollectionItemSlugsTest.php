<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use c975L\SiteBundle\Service\CollectionItemSlugs;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

class CollectionItemSlugsTest extends TestCase
{
    // Accents, spaces and uppercase normalized
    public function testNormalizesAccentsSpacesAndCase(): void
    {
        $item = new CollectionItem()->setCollectionGroup(new CollectionGroup())->setSlug('Héllo Wörld!');

        $this->slugs()->apply($item);

        $this->assertSame('hello-world', $item->getSlug());
    }

    // An item without a slug is left as it is
    public function testDoesNothingWhenSlugIsNull(): void
    {
        $item = new CollectionItem();

        $this->slugs()->apply($item);

        $this->assertNull($item->getSlug());
    }

    // Collision check is scoped to the item's own collection - a same-named slug in another collection never collides
    public function testAppendsASuffixOnCollisionWithinTheSameCollectionGroup(): void
    {
        $collectionGroup = $this->withId(new CollectionGroup(), 1);
        $repository = $this->createStub(CollectionItemRepository::class);
        $repository->method('findOneByCollectionGroupAndSlug')->willReturnMap([
            [$collectionGroup, 'item-1', $this->withId(new CollectionItem(), 1)],
            [$collectionGroup, 'item-1-2', null],
        ]);
        $item = new CollectionItem()->setCollectionGroup($collectionGroup)->setSlug('Item 1');

        $this->slugs($repository)->apply($item);

        $this->assertSame('item-1-2', $item->getSlug());
    }

    // Re-saving an item with its own unchanged slug must not collide with itself
    public function testDoesNotCollideWithItself(): void
    {
        $collectionGroup = new CollectionGroup();
        $repository = $this->createStub(CollectionItemRepository::class);
        $repository->method('findOneByCollectionGroupAndSlug')->willReturn($this->withId(
            new CollectionItem()->setCollectionGroup($collectionGroup)->setSlug('item-1'),
            5
        ));
        $item = $this->withId(new CollectionItem()->setCollectionGroup($collectionGroup)->setSlug('Item 1'), 5);

        $this->slugs($repository)->apply($item);

        $this->assertSame('item-1', $item->getSlug());
    }

    // An item not yet in a collection never collides
    public function testNeverCollidesWhenTheItemHasNoCollectionGroupYet(): void
    {
        $item = new CollectionItem()->setSlug('Item 1');

        $this->slugs()->apply($item);

        $this->assertSame('item-1', $item->getSlug());
    }

    // The service over a repository holding nothing unless given
    private function slugs(?CollectionItemRepository $repository = null): CollectionItemSlugs
    {
        return new CollectionItemSlugs($repository ?? $this->createStub(CollectionItemRepository::class), new AsciiSlugger());
    }

    // An entity given the id the database would have given it
    /**
     * @template T of object
     *
     * @param T $entity
     *
     * @return T
     */
    private function withId(object $entity, int $id): object
    {
        new \ReflectionProperty($entity::class, 'id')->setValue($entity, $id);

        return $entity;
    }
}
