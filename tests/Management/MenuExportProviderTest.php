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
use c975L\SiteBundle\Management\BlockDataExporter;
use c975L\SiteBundle\Management\MenuExportProvider;
use c975L\SiteBundle\Management\MenuImportProvider;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\UiBundle\Entity\Block;
use PHPUnit\Framework\TestCase;

class MenuExportProviderTest extends TestCase
{
    public function testGetKindMatchesMenuImportProvider(): void
    {
        $provider = new MenuExportProvider($this->createStub(MenuRepository::class), new BlockDataExporter(sys_get_temp_dir()));

        $this->assertSame(MenuImportProvider::KIND, $provider->getKind());
    }

    public function testExportAllSerializesEveryMenuFromTheRepository(): void
    {
        $block = (new Block())->setKind('menu_link')->setPosition(0)->setData(['label' => 'Home', 'url' => '/']);
        $menu = (new Menu())->setLocation(Menu::LOCATION_NAVBAR);
        $menu->addBlock($block);

        $menuRepository = $this->createMock(MenuRepository::class);
        $menuRepository->expects($this->once())->method('findAll')->willReturn([$menu]);

        $data = (new MenuExportProvider($menuRepository, new BlockDataExporter(sys_get_temp_dir())))->exportAll();

        $this->assertSame(Menu::LOCATION_NAVBAR, $data['items'][0]['location']);
        $this->assertSame('menu_link', $data['items'][0]['blocks'][0]['kind']);
        $this->assertSame(['label' => 'Home', 'url' => '/'], $data['items'][0]['blocks'][0]['data']);
        $this->assertSame([], $data['files']);
    }
}
