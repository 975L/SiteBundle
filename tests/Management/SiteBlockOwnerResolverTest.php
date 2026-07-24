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
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\SiteBlockOwnerResolver;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\SiteBundle\Repository\PageRepository;
use PHPUnit\Framework\TestCase;

class SiteBlockOwnerResolverTest extends TestCase
{
    public function testSupportsPageAndMenuOnly(): void
    {
        $resolver = new SiteBlockOwnerResolver(
            $this->createStub(PageRepository::class),
            $this->createStub(MenuRepository::class)
        );

        $this->assertTrue($resolver->supports('page'));
        $this->assertTrue($resolver->supports('menu'));
        $this->assertFalse($resolver->supports('book'));
    }

    public function testFindPageDelegatesToThePageRepository(): void
    {
        $page = new Page();
        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->expects($this->once())->method('find')->with(42)->willReturn($page);
        $menuRepository = $this->createMock(MenuRepository::class);
        $menuRepository->expects($this->never())->method('find');

        $resolver = new SiteBlockOwnerResolver($pageRepository, $menuRepository);

        $this->assertSame($page, $resolver->find('page', 42));
    }

    public function testFindMenuDelegatesToTheMenuRepository(): void
    {
        $menu = new Menu();
        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->expects($this->never())->method('find');
        $menuRepository = $this->createMock(MenuRepository::class);
        $menuRepository->expects($this->once())->method('find')->with(7)->willReturn($menu);

        $resolver = new SiteBlockOwnerResolver($pageRepository, $menuRepository);

        $this->assertSame($menu, $resolver->find('menu', 7));
    }

    public function testFindReturnsNullForAnUnsupportedOwnerType(): void
    {
        $resolver = new SiteBlockOwnerResolver(
            $this->createStub(PageRepository::class),
            $this->createStub(MenuRepository::class)
        );

        $this->assertNull($resolver->find('book', 1));
    }
}
