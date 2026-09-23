<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Service\MenuBlockCacheTagProvider;
use c975L\SiteBundle\Twig\MenuExtension;
use c975L\UiBundle\Entity\Block;
use PHPUnit\Framework\TestCase;

class MenuBlockCacheTagProviderTest extends TestCase
{
    // The link's target and label are what MenuExtension decides on - the provider only reads them off the block
    public function testAMenuLinkIsResolvedFromItsTargetAndLabel(): void
    {
        $extension = $this->createMock(MenuExtension::class);
        $extension->expects($this->once())->method('getMenuLinkCacheTags')->with('page:42', 'Contact')->willReturn(['menus_all']);

        $resolvers = new MenuBlockCacheTagProvider($extension)->getCacheTagResolvers();

        $this->assertSame(['menu_link'], array_keys($resolvers));
        $this->assertSame(['menus_all'], $resolvers['menu_link'](new Block()->setKind('menu_link')->setData(['target' => 'page:42', 'label' => 'Contact'])));
    }
}
