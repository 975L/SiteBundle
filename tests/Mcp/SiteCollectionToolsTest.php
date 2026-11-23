<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Mcp;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Mcp\SiteCollectionTools;
use c975L\SiteBundle\Mcp\SiteMcpAccess;
use c975L\SiteBundle\Repository\CollectionGroupRepository;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use c975L\SiteBundle\Service\CollectionItemSlugs;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\ConstraintViolation;
use Symfony\Component\Validator\ConstraintViolationList;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SiteCollectionToolsTest extends TestCase
{
    // The tools named, the writing ones flagged, none deleting, the live change said plainly
    public function testTools(): void
    {
        $tools = iterator_to_array($this->tools()->getMcpTools(), false);

        $this->assertSame(['site_collection_list', 'site_collection_get', 'site_collection_item_create', 'site_collection_item_edit'], array_map(static fn ($tool): string => $tool->name, $tools));
        $this->assertSame([false, false, true, true], array_map(static fn ($tool): bool => $tool->writes, $tools));
        $this->assertSame([false, false, false, false], array_map(static fn ($tool): bool => $tool->destroys, $tools));
        $this->assertStringContainsString('shows on the site at once', $tools[3]->description);
    }

    // Every collection with its count of items, one without any counting none
    public function testList(): void
    {
        $groups = $this->createStub(CollectionGroupRepository::class);
        $groups->method('findBy')->willReturn([$this->group(1, 'catalog'), $this->group(2, 'item-2')]);
        $items = $this->createStub(CollectionItemRepository::class);
        $items->method('countByCollectionGroups')->willReturn([1 => 3]);

        $listed = $this->tools($groups, $items)->list();

        $this->assertSame([['id' => 1, 'name' => 'catalog', 'slug' => 'catalog', 'items' => 3], ['id' => 2, 'name' => 'item-2', 'slug' => 'item-2', 'items' => 0]], $listed);
    }

    // A collection read with its items in their order
    public function testGet(): void
    {
        $group = $this->group(1, 'catalog');
        $groups = $this->createStub(CollectionGroupRepository::class);
        $groups->method('find')->willReturn($group);
        $items = $this->createStub(CollectionItemRepository::class);
        $items->method('findByCollectionGroup')->willReturn([
            new CollectionItem()->setCollectionGroup($group)->setTitle('item-1')->setSlug('item-1')->setUrl('/pages/item-1'),
            new CollectionItem()->setCollectionGroup($group)->setTitle('item-2')->setSlug('item-2')->setPosition(1)->setFilename('item-2.webp'),
        ]);

        $read = $this->tools($groups, $items)->get(1);

        $this->assertSame('catalog', $read['name']);
        $this->assertSame(['item-1', 'item-2'], array_column($read['items'], 'title'));
        $this->assertSame(['/pages/item-1', null], array_column($read['items'], 'url'));
        $this->assertSame([false, true], array_column($read['items'], 'image'));
    }

    // An unknown collection is refused, by every tool reading one
    public function testUnknownCollection(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessageIs('No collection 9.');
        $this->tools()->get(9);
    }

    // An item added at the end of its collection, its slug kept unique there, nothing published since an item has no such state
    public function testCreate(): void
    {
        $group = $this->group(1, 'catalog');
        $groups = $this->createStub(CollectionGroupRepository::class);
        $groups->method('find')->willReturn($group);
        $items = $this->createStub(CollectionItemRepository::class);
        $items->method('countByCollectionGroup')->willReturn(2);
        // Another item of the collection holding the slug, an id of its own telling it from the new one
        $held = new CollectionItem();
        new \ReflectionProperty(CollectionItem::class, 'id')->setValue($held, 7);
        $items->method('findOneByCollectionGroupAndSlug')->willReturnCallback(static fn (CollectionGroup $group, string $slug): ?CollectionItem => 'item-1' === $slug ? $held : null);
        $persisted = [];
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $created = $this->tools($groups, $items, $entityManager)->create(1, ' Item 1 ', ' description-1 ', '');

        $this->assertInstanceOf(CollectionItem::class, $persisted[0]);
        $this->assertSame($group, $persisted[0]->getCollectionGroup());
        $this->assertSame(['Item 1', 'item-1-2', 'description-1', null, 2, false], [$created['title'], $created['slug'], $created['description'], $created['url'], $created['position'], $created['image']]);
        $this->assertNull($persisted[0]->getFile());
    }

    // What the entity's constraints refuse on a new item is named and nothing is written
    public function testCreateRefusesWhatTheConstraintsRefuse(): void
    {
        $groups = $this->createStub(CollectionGroupRepository::class);
        $groups->method('find')->willReturn($this->group(1, 'catalog'));
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessageIs('url: label.url_scheme_not_allowed');
        $this->tools($groups, entityManager: $entityManager, validator: $this->refusing('url', 'label.url_scheme_not_allowed'))->create(1, 'item-1', url: 'javascript:alert(1)');
    }

    // Only the texts given change, an empty link clearing it, the slug and the place kept
    public function testEdit(): void
    {
        $item = new CollectionItem()->setCollectionGroup($this->group(1, 'catalog'))->setTitle('item-1')->setSlug('item-1')->setDescription('description-1')->setUrl('https://example.com')->setPosition(4);
        $items = $this->createStub(CollectionItemRepository::class);
        $items->method('find')->willReturn($item);

        $edited = $this->tools(items: $items)->edit(1, title: 'Item 2', url: '');

        $this->assertSame(['Item 2', 'item-1', 'description-1', null, 4], [$edited['title'], $edited['slug'], $edited['description'], $edited['url'], $edited['position']]);
    }

    // A refused change is named, taken back off the managed item and never flushed
    public function testEditRefusesWhatTheConstraintsRefuse(): void
    {
        $item = new CollectionItem()->setTitle('item-1')->setSlug('item-1');
        $items = $this->createStub(CollectionItemRepository::class);
        $items->method('find')->willReturn($item);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('refresh')->with($item);
        $entityManager->expects($this->never())->method('flush');

        $this->expectException(ToolCallException::class);
        $this->tools(items: $items, entityManager: $entityManager, validator: $this->refusing('url', 'label.url_scheme_not_allowed'))->edit(1, url: 'javascript:alert(1)');
    }

    // An unknown item is refused
    public function testUnknownItem(): void
    {
        $this->expectException(ToolCallException::class);
        $this->expectExceptionMessageIs('No collection item 9.');
        $this->tools()->edit(9, title: 'item-1');
    }

    // An account that does not edit the collections is refused every tool
    public function testRefusedWithoutTheRole(): void
    {
        $tools = $this->tools(granted: false);
        foreach ([$tools->list(...), static fn () => $tools->get(1), static fn () => $tools->create(1, 'item-1'), static fn () => $tools->edit(1, 'item-1')] as $call) {
            try {
                $call();
                $this->fail('The tool answered an account without the role.');
            } catch (ToolCallException $exception) {
                $this->assertSame('This account may not work on the collections.', $exception->getMessage());
            }
        }
    }

    // A validator refusing one property
    private function refusing(string $property, string $message): ValidatorInterface
    {
        $validator = $this->createStub(ValidatorInterface::class);
        $validator->method('validate')->willReturn(new ConstraintViolationList([new ConstraintViolation($message, null, [], null, $property, null)]));

        return $validator;
    }

    // A collection with the id the database would have given it
    private function group(int $id, string $name): CollectionGroup
    {
        $group = new CollectionGroup()->setName($name)->setSlug($name);
        new \ReflectionProperty(CollectionGroup::class, 'id')->setValue($group, $id);

        return $group;
    }

    // The tools for an account editing the collections or not, a validator passing everything unless given
    private function tools(?CollectionGroupRepository $groups = null, ?CollectionItemRepository $items = null, ?EntityManagerInterface $entityManager = null, ?ValidatorInterface $validator = null, bool $granted = true): SiteCollectionTools
    {
        $items ??= $this->createStub(CollectionItemRepository::class);
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn('ROLE_EDITOR');
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($granted);
        if (null === $validator) {
            $validator = $this->createStub(ValidatorInterface::class);
            $validator->method('validate')->willReturn(new ConstraintViolationList());
        }

        return new SiteCollectionTools(
            $groups ?? $this->createStub(CollectionGroupRepository::class),
            $items,
            new CollectionItemSlugs($items, new AsciiSlugger()),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $validator,
            new SiteMcpAccess($config, $security),
        );
    }
}
