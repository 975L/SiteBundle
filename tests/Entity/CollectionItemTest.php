<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Entity;

use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Entity\CollectionItem;
use PHPUnit\Framework\TestCase;

class CollectionItemTest extends TestCase
{
    private function collectionGroup(string $slug): CollectionGroup
    {
        return (new CollectionGroup())->setName(ucfirst($slug))->setSlug($slug);
    }

    public function testGetImageWidthIsAlways800(): void
    {
        $item = new CollectionItem();

        $this->assertSame(800, $item->getImageWidth());
    }

    public function testGetVichMediaPathIncludesCollectionSlugAndUniqidWhenIdIsNotYetAssigned(): void
    {
        $item = (new CollectionItem())->setCollectionGroup($this->collectionGroup('projects'));

        $this->assertMatchesRegularExpression(
            '#^medias/site/collection-projects-[a-f0-9]+$#',
            $item->getVichMediaPath()
        );
    }

    public function testGetVichMediaPathUsesTheRealIdOnceAssigned(): void
    {
        $item = (new CollectionItem())->setCollectionGroup($this->collectionGroup('projects'));
        (new \ReflectionProperty(CollectionItem::class, 'id'))->setValue($item, 42);

        $this->assertSame('medias/site/collection-projects-42', $item->getVichMediaPath());
    }

    public function testToStringReturnsTheTitle(): void
    {
        $item = (new CollectionItem())->setTitle('Papa Câlin');

        $this->assertSame('Papa Câlin', (string) $item);
    }

    public function testToStringReturnsEmptyStringWhenNoTitleIsSet(): void
    {
        $this->assertSame('', (string) new CollectionItem());
    }

    public function testSettersAreFluentAndGettersReflectTheirValue(): void
    {
        $collectionGroup = $this->collectionGroup('projects');

        $item = (new CollectionItem())
            ->setCollectionGroup($collectionGroup)
            ->setTitle('Papa Câlin')
            ->setSlug('papa-calin')
            ->setDescription('Des histoires inventées')
            ->setUrl('https://papa-calin.com')
            ->setPosition(3);

        $this->assertSame($collectionGroup, $item->getCollectionGroup());
        $this->assertSame('Papa Câlin', $item->getTitle());
        $this->assertSame('papa-calin', $item->getSlug());
        $this->assertSame('Des histoires inventées', $item->getDescription());
        $this->assertSame('https://papa-calin.com', $item->getUrl());
        $this->assertSame(3, $item->getPosition());
    }
}
