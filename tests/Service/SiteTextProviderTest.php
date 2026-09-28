<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Service\CollectionItemTranslator;
use c975L\SiteBundle\Service\PageTranslator;
use c975L\SiteBundle\Service\SiteTextProvider;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Service\BlockTextCollector;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\EntityRepository;
use PHPUnit\Framework\TestCase;

// What this bundle hands the translate command: a page's own texts, the menus' blocks and a collection item's card, a field left empty having nothing to translate
class SiteTextProviderTest extends TestCase
{
    /** @param list<object> $rows */
    private function repository(array $rows): EntityRepository
    {
        $repository = $this->createStub(EntityRepository::class);
        $repository->method('findBy')->willReturn($rows);
        $repository->method('findAll')->willReturn($rows);

        return $repository;
    }

    public function testItHandsThePagesAndTheCollectionItems(): void
    {
        $page = new Page();
        new \ReflectionProperty(Page::class, 'id')->setValue($page, 4);
        $page->setSlug('home');
        $page->setTitle('Accueil');

        $item = new CollectionItem();
        new \ReflectionProperty(CollectionItem::class, 'id')->setValue($item, 9);
        $item->setTitle('Un atelier');
        $item->setDescription('  ');

        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturnMap([
            [Page::class, $this->repository([$page])],
            [Menu::class, $this->repository([])],
            [CollectionItem::class, $this->repository([$item])],
        ]);

        $rows = [...new SiteTextProvider($manager, new BlockTextCollector($this->createStub(BlockRegistry::class)))->getTranslatableTexts()];

        $this->assertSame(
            [[PageTranslator::OWNER, 4, 'title', 'Accueil'], [CollectionItemTranslator::OWNER, 9, 'title', 'Un atelier']],
            array_map(static fn (array $row): array => [$row['owner'], $row['ownerId'], $row['field'], $row['source']], $rows),
        );
    }

    // A menu's links are block texts like a page's, labelled after the menu's location
    public function testItHandsTheMenusBlocks(): void
    {
        $block = new Block();
        new \ReflectionProperty(Block::class, 'id')->setValue($block, 12);
        $block->setKind('menu_link');
        $block->setData(['label' => 'Nous contacter']);

        $menu = new Menu();
        $menu->setLocation(Menu::LOCATION_NAVBAR);
        $menu->addBlock($block);

        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('getRepository')->willReturnMap([
            [Page::class, $this->repository([])],
            [Menu::class, $this->repository([$menu])],
            [CollectionItem::class, $this->repository([])],
        ]);

        $registry = $this->createStub(BlockRegistry::class);
        $registry->method('getLabel')->willReturn('Link');
        $registry->method('getTranslatable')->willReturn(['label']);
        $registry->method('getTranslatableCollections')->willReturn([]);

        $rows = [...new SiteTextProvider($manager, new BlockTextCollector($registry))->getTranslatableTexts()];

        $this->assertSame(
            [['owner' => Translation::OWNER_BLOCK, 'ownerId' => 12, 'field' => 'label', 'source' => 'Nous contacter', 'label' => 'Menu navbar / Link']],
            $rows,
        );
    }
}
