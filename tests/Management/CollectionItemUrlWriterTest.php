<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Management\CollectionItemUrlWriter;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Validator\Validation;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class CollectionItemUrlWriterTest extends TestCase
{
    // A value that is no text never reaches the setter, whose type would refuse it with a crash
    public function testAValueThatIsNoTextIsDropped(): void
    {
        $validator = $this->createStub(ValidatorInterface::class);
        $item = new CollectionItem()->setUrl('https://example.com/held');

        $this->assertFalse(new CollectionItemUrlWriter($validator)->write($item, ['javascript:alert(1)']));
        $this->assertNull($item->getUrl());
    }

    /** @return iterable<string, array{mixed, bool}> */
    public static function urls(): iterable
    {
        yield 'none' => [null, true];
        yield 'an outside address' => ['https://example.com/a', true];
        yield 'a path of the site' => ['/projects/alpha', true];
        yield 'a page slug' => ['a-propos', true];
        yield 'a page target' => ['page:12#offer', true];
        yield 'a route target' => ['route:shop_index', true];
        yield 'a mail address' => ['mailto:contact@example.com', true];
        yield 'javascript' => ['javascript:alert(1)', false];
        yield 'tab in the scheme' => ["java\tscript:alert(1)", false];
        yield 'encoded scheme' => ['java&#115;cript:alert(1)', false];
        yield 'data' => ['data:text/html,x', false];
        yield 'too long for the column' => ['https://example.com/' . str_repeat('a', 255), false];
    }

    // Through the real constraints of CollectionItem::$url, read off the entity
    #[DataProvider('urls')]
    public function testTheEntitysOwnConstraintsDecide(mixed $url, bool $kept): void
    {
        $validator = Validation::createValidatorBuilder()->enableAttributeMapping()->getValidator();
        $item = new CollectionItem();

        $this->assertSame($kept, new CollectionItemUrlWriter($validator)->write($item, $url));
        $this->assertSame($kept ? $url : null, $item->getUrl());
    }
}
