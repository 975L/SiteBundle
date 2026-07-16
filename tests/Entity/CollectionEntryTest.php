<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Entity;

use c975L\SiteBundle\Entity\CollectionEntry;
use PHPUnit\Framework\TestCase;

class CollectionEntryTest extends TestCase
{
    public function testGetImageWidthIsAlways800(): void
    {
        $entry = new CollectionEntry();

        $this->assertSame(800, $entry->getImageWidth());
    }

    public function testGetVichMediaPathIncludesGroupAndUniqidWhenIdIsNotYetAssigned(): void
    {
        $entry = (new CollectionEntry())->setGroup('projects');

        $this->assertMatchesRegularExpression(
            '#^medias/site/collection-projects-[a-f0-9]+$#',
            $entry->getVichMediaPath()
        );
    }

    public function testGetVichMediaPathUsesTheRealIdOnceAssigned(): void
    {
        $entry = (new CollectionEntry())->setGroup('projects');
        (new \ReflectionProperty(CollectionEntry::class, 'id'))->setValue($entry, 42);

        $this->assertSame('medias/site/collection-projects-42', $entry->getVichMediaPath());
    }

    public function testToStringReturnsTheTitle(): void
    {
        $entry = (new CollectionEntry())->setTitle('Papa Câlin');

        $this->assertSame('Papa Câlin', (string) $entry);
    }

    public function testToStringReturnsEmptyStringWhenNoTitleIsSet(): void
    {
        $this->assertSame('', (string) new CollectionEntry());
    }

    public function testSettersAreFluentAndGettersReflectTheirValue(): void
    {
        $entry = (new CollectionEntry())
            ->setGroup('projects')
            ->setTitle('Papa Câlin')
            ->setDescription('Des histoires inventées')
            ->setUrl('https://papa-calin.com')
            ->setPosition(3);

        $this->assertSame('projects', $entry->getGroup());
        $this->assertSame('Papa Câlin', $entry->getTitle());
        $this->assertSame('Des histoires inventées', $entry->getDescription());
        $this->assertSame('https://papa-calin.com', $entry->getUrl());
        $this->assertSame(3, $entry->getPosition());
    }
}
