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
use c975L\SiteBundle\Management\MenuBlockEditUrlProvider;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\UiBundle\Entity\Block;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

class MenuBlockEditUrlProviderTest extends TestCase
{
    private function createProvider(array $menusOwningBlocks): MenuBlockEditUrlProvider
    {
        return new MenuBlockEditUrlProvider(
            $this->createMenuRepository($menusOwningBlocks),
            $this->createAdminUrlGenerator(),
        );
    }

    private function createMenuRepository(array $menusOwningBlocks): MenuRepository
    {
        $repository = $this->createStub(MenuRepository::class);
        $repository->method('findByBlockIds')->willReturn($menusOwningBlocks);

        return $repository;
    }

    // Every setter returns the generator itself (BlockFocusUrl chains them), and generateUrl() echoes back whatever focusBlock was set - what matters here is which block the button points at
    private function createAdminUrlGenerator(): AdminUrlGeneratorInterface
    {
        $focusBlock = null;

        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setController')->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('setEntityId')->willReturnSelf();
        $generator->method('set')->willReturnCallback(function (string $key, mixed $value) use ($generator, &$focusBlock) {
            $focusBlock = $value;

            return $generator;
        });
        $generator->method('generateUrl')->willReturnCallback(function () use (&$focusBlock): string {
            return '/admin/menu?focusBlock=' . $focusBlock;
        });

        return $generator;
    }

    private function blockWithId(int $id): Block
    {
        $block = new Block();
        (new \ReflectionProperty(Block::class, 'id'))->setValue($block, $id);

        return $block;
    }

    private function menuWithBlocks(string $location, Block ...$blocks): Menu
    {
        $menu = new Menu();
        $menu->setLocation($location);
        foreach ($blocks as $block) {
            $menu->addBlock($block);
        }

        return $menu;
    }

    // A footer block resolves to the footer menu's edit screen, focused on that very block's row
    public function testGetEditUrlsResolvesUrlForBlockOwnedByFooterMenu(): void
    {
        $block = $this->blockWithId(10);

        $provider = $this->createProvider([$this->menuWithBlocks(Menu::LOCATION_FOOTER, $block)]);

        $this->assertSame([10 => '/admin/menu?focusBlock=10'], $provider->getEditUrls([$block]));
    }

    // The navbar is hovered just to navigate, so its links get no button - see the provider's own comment
    public function testGetEditUrlsIgnoresNavbarMenu(): void
    {
        $block = $this->blockWithId(20);

        $provider = $this->createProvider([$this->menuWithBlocks(Menu::LOCATION_NAVBAR, $block)]);

        $this->assertSame([], $provider->getEditUrls([$block]));
    }

    // Only the hovered blocks are answered for, not every block the same footer holds
    public function testGetEditUrlsAnswersOnlyForRequestedBlocks(): void
    {
        $requested = $this->blockWithId(30);
        $other = $this->blockWithId(31);

        $provider = $this->createProvider([$this->menuWithBlocks(Menu::LOCATION_FOOTER, $requested, $other)]);

        $this->assertSame([30 => '/admin/menu?focusBlock=30'], $provider->getEditUrls([$requested]));
    }

    // A block no menu owns (a Page's own block, which SiteBlockEditUrlProvider answers for) resolves to nothing - no error
    public function testGetEditUrlsReturnsEmptyArrayForUnownedBlock(): void
    {
        $provider = $this->createProvider([]);

        $this->assertSame([], $provider->getEditUrls([$this->blockWithId(40)]));
    }

    // Passing no blocks skips the repository query entirely
    public function testGetEditUrlsReturnsEmptyArrayForNoBlocks(): void
    {
        $provider = $this->createProvider([]);

        $this->assertSame([], $provider->getEditUrls([]));
    }
}
