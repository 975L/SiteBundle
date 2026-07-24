<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Management\BlockDataImporter;
use c975L\SiteBundle\Management\MenuImportProvider;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\SiteBundle\Service\DefaultPagesImporter;
use c975L\UiBundle\Entity\Block;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class MenuImportProviderTest extends TestCase
{
    private function createMenuRepository(?Menu $existingMenu = null): MenuRepository
    {
        $repository = $this->createStub(MenuRepository::class);
        $repository->method('findOneByLocation')->willReturn($existingMenu);

        return $repository;
    }

    public function testSupportsImportOnlyMatchesSiteMenuKind(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $provider = new MenuImportProvider($em, $this->createMenuRepository(), new BlockDataImporter($em, $this->createStub(DefaultPagesImporter::class)));

        $this->assertTrue($provider->supportsImport('site_menu'));
        $this->assertFalse($provider->supportsImport('site_page'));
    }

    public function testImportCreatesANewMenuWithItsBlocks(): void
    {
        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $provider = new MenuImportProvider($em, $this->createMenuRepository(), new BlockDataImporter($em, $this->createStub(DefaultPagesImporter::class)));

        $result = $provider->import([[
            'location' => Menu::LOCATION_FOOTER,
            'blocks' => [
                ['kind' => 'menu_link', 'position' => 0, 'data' => ['label' => 'Contact', 'url' => '/contact']],
            ],
        ]]);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);

        $menu = null;
        foreach ($persisted as $entity) {
            if ($entity instanceof Menu) {
                $menu = $entity;
            }
        }
        $this->assertInstanceOf(Menu::class, $menu);
        $this->assertSame(Menu::LOCATION_FOOTER, $menu->getLocation());
        $this->assertCount(1, $menu->getBlocks());
        $this->assertSame('menu_link', $menu->getBlocks()->first()->getKind());
    }

    public function testImportOverwritesAnExistingMenuAndReplacesItsBlocks(): void
    {
        $existingBlock = (new Block())->setKind('menu_link')->setPosition(0)->setData(['label' => 'Old', 'url' => '/old']);
        $existingMenu = (new Menu())->setLocation(Menu::LOCATION_NAVBAR);
        $existingMenu->addBlock($existingBlock);

        $em = $this->createStub(EntityManagerInterface::class);
        $provider = new MenuImportProvider($em, $this->createMenuRepository($existingMenu), new BlockDataImporter($em, $this->createStub(DefaultPagesImporter::class)));

        $result = $provider->import([[
            'location' => Menu::LOCATION_NAVBAR,
            'blocks' => [
                ['kind' => 'menu_link', 'position' => 0, 'data' => ['label' => 'New', 'url' => '/new']],
            ],
        ]]);

        $this->assertSame(['created' => 0, 'updated' => 1], $result);
        $this->assertCount(1, $existingMenu->getBlocks());
        $this->assertSame('New', $existingMenu->getBlocks()->first()->getData()['label']);
    }
}
