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
use PHPUnit\Framework\TestCase;

class CollectionGroupTest extends TestCase
{
    public function testToStringReturnsTheName(): void
    {
        $collectionGroup = (new CollectionGroup())->setName('Projects');

        $this->assertSame('Projects', (string) $collectionGroup);
    }

    public function testToStringReturnsEmptyStringWhenNoNameIsSet(): void
    {
        $this->assertSame('', (string) new CollectionGroup());
    }

    public function testSettersAreFluentAndGettersReflectTheirValue(): void
    {
        $collectionGroup = (new CollectionGroup())
            ->setName('Projects')
            ->setSlug('projects');

        $this->assertSame('Projects', $collectionGroup->getName());
        $this->assertSame('projects', $collectionGroup->getSlug());
    }
}
