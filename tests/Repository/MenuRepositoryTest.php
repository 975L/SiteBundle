<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Repository;

use c975L\ConfigBundle\Contract\UserInterface;
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\SiteBundle\Tests\Repository\Entity\PageAuthorFixture;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\BlockRepository;
use Doctrine\Common\EventManager;
use Doctrine\DBAL\DriverManager;
use Doctrine\ORM\EntityManager;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\UnderscoreNamingStrategy;
use Doctrine\ORM\ORMSetup;
use Doctrine\ORM\Tools\ResolveTargetEntityListener;
use Doctrine\ORM\Tools\SchemaTool;
use Doctrine\Persistence\ManagerRegistry;
use PHPUnit\Framework\Attributes\RequiresPhpExtension;
use PHPUnit\Framework\TestCase;

// The menus owning given blocks, run on SQLite in memory rather than on a stub that would only echo what it was told
#[RequiresPhpExtension('pdo_sqlite')]
class MenuRepositoryTest extends TestCase
{
    private EntityManagerInterface $entityManager;

    private MenuRepository $repository;

    protected function setUp(): void
    {
        $uiEntities = \dirname(new \ReflectionClass(Block::class)->getFileName());
        $config = ORMSetup::createAttributeMetadataConfig([\dirname(__DIR__, 2) . '/src/Entity', $uiEntities, __DIR__ . '/Entity'], true);
        $config->enableNativeLazyObjects(true);
        $config->setNamingStrategy(new UnderscoreNamingStrategy(\CASE_LOWER, true));
        // The editor a Block names by its interface, the application's User standing behind it
        $resolver = new ResolveTargetEntityListener();
        $resolver->addResolveTargetEntity(UserInterface::class, PageAuthorFixture::class, []);
        $eventManager = new EventManager();
        $eventManager->addEventSubscriber($resolver);
        $this->entityManager = new EntityManager(DriverManager::getConnection(['driver' => 'pdo_sqlite', 'memory' => true], $config), $config, $eventManager);
        new SchemaTool($this->entityManager)->createSchema(array_map($this->entityManager->getClassMetadata(...), [Menu::class, Block::class, Media::class, PageAuthorFixture::class]));

        $registry = $this->createStub(ManagerRegistry::class);
        $registry->method('getManagerForClass')->willReturn($this->entityManager);
        $this->repository = new MenuRepository($registry, $this->createStub(BlockRepository::class));
    }

    // A menu found by one of its blocks comes back with all its blocks read in the same query, the edit buttons walking them
    public function testALookupByBlockReadsEveryBlockOfItsMenuUpFront(): void
    {
        $menu = new Menu()->setLocation('footer');
        $menu->addBlock(new Block()->setKind('menu_link')->setPosition(0));
        $menu->addBlock(new Block()->setKind('menu_link')->setPosition(1));
        $this->entityManager->persist($menu);
        $this->entityManager->flush();
        $blockId = $menu->getBlocks()->first()->getId();
        $this->entityManager->clear();

        $menus = $this->repository->findByBlockIds([$blockId]);

        $this->assertCount(1, $menus);
        $this->assertTrue($menus[0]->getBlocks()->isInitialized());
        $this->assertCount(2, $menus[0]->getBlocks());
    }
}
