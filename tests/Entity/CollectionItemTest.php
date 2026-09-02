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
use Symfony\Component\HttpFoundation\File\File;

class CollectionItemTest extends TestCase
{
    private function collectionGroup(string $slug): CollectionGroup
    {
        return new CollectionGroup()->setName(ucfirst($slug))->setSlug($slug);
    }

    public function testGetImageWidthIsAlways800(): void
    {
        $item = new CollectionItem();

        $this->assertSame(800, $item->getImageWidth());
    }

    public function testGetVichMediaPathIncludesCollectionSlugAndUniqidWhenIdIsNotYetAssigned(): void
    {
        $item = new CollectionItem()->setCollectionGroup($this->collectionGroup('projects'));

        $this->assertMatchesRegularExpression(
            '#^medias/site/collection-projects-[a-f0-9]+$#',
            $item->getVichMediaPath()
        );
    }

    public function testGetVichMediaPathUsesTheRealIdOnceAssigned(): void
    {
        $item = new CollectionItem()->setCollectionGroup($this->collectionGroup('projects'));
        new \ReflectionProperty(CollectionItem::class, 'id')->setValue($item, 42);

        $this->assertSame('medias/site/collection-projects-42', $item->getVichMediaPath());
    }

    public function testToStringReturnsTheTitle(): void
    {
        $item = new CollectionItem()->setTitle('Projet Alpha');

        $this->assertSame('Projet Alpha', (string) $item);
    }

    public function testToStringReturnsEmptyStringWhenNoTitleIsSet(): void
    {
        $this->assertSame('', (string) new CollectionItem());
    }

    public function testGetUpdatedAtIsNullUntilAFileIsSet(): void
    {
        $this->assertNull(new CollectionItem()->getUpdatedAt());
    }

    public function testSetFileStampsUpdatedAtSoVichSeesTheEntityAsChanged(): void
    {
        $item = new CollectionItem()->setFile(new File(__FILE__));

        $this->assertInstanceOf(\DateTimeImmutable::class, $item->getUpdatedAt());
    }

    public function testSetFileWithNullLeavesUpdatedAtUntouched(): void
    {
        $item = new CollectionItem()->setFile(null);

        $this->assertNull($item->getUpdatedAt());
    }

    public function testSettersAreFluentAndGettersReflectTheirValue(): void
    {
        $collectionGroup = $this->collectionGroup('projects');

        $item = new CollectionItem()
            ->setCollectionGroup($collectionGroup)
            ->setTitle('Projet Alpha')
            ->setSlug('projet-alpha')
            ->setDescription('Des histoires inventées')
            ->setUrl('https://projet-alpha.example')
            ->setPosition(3);

        $this->assertSame($collectionGroup, $item->getCollectionGroup());
        $this->assertSame('Projet Alpha', $item->getTitle());
        $this->assertSame('projet-alpha', $item->getSlug());
        $this->assertSame('Des histoires inventées', $item->getDescription());
        $this->assertSame('https://projet-alpha.example', $item->getUrl());
        $this->assertSame(3, $item->getPosition());
    }
}
