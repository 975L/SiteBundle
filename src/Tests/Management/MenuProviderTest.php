<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Controller\Management\MenuCrudController;
use c975L\SiteBundle\Controller\Management\PageCrudController;
use c975L\SiteBundle\Controller\Management\RedirectCrudController;
use c975L\SiteBundle\Controller\Management\SiteGraphicCrudController;
use c975L\SiteBundle\Controller\Management\UserCrudController;
use c975L\SiteBundle\Management\MenuProvider;
use c975L\UiBundle\Controller\Management\MediaCrudController;
use PHPUnit\Framework\TestCase;

// Lives under src/Tests (not a sibling tests/ dir) so it stays autoloadable by consuming apps,
// whose attribute route loader recursively reflects every class under the bundle root
class MenuProviderTest extends TestCase
{
    // The dashboard section this bundle contributes must carry the 'site' translation domain
    public function testGetMenuSectionReturnsLabelAndDomain(): void
    {
        $section = (new MenuProvider())->getMenuSection();

        $this->assertSame('label.management', $section['label']);
        $this->assertSame('site', $section['translation_domain']);
    }

    // Every CRUD entry this bundle contributes to the dashboard, including UiBundle's media library
    // (wired here because UiBundle can't register its own menu item without a circular dependency)
    public function testGetMenusReturnsEveryContributedControllerEntry(): void
    {
        $menus = (new MenuProvider())->getMenus();

        $this->assertSame(PageCrudController::class, $menus['page']['controller']);
        $this->assertSame(RedirectCrudController::class, $menus['redirect']['controller']);
        $this->assertSame(UserCrudController::class, $menus['user']['controller']);
        $this->assertSame(SiteGraphicCrudController::class, $menus['site_graphic']['controller']);
        $this->assertSame(MenuCrudController::class, $menus['menu']['controller']);
        $this->assertSame(MediaCrudController::class, $menus['media']['controller']);
        $this->assertSame('ui', $menus['media']['translation_domain']);
    }

    // This provider contributes no standalone links (only CRUD menus)
    public function testGetLinksReturnsEmptyArray(): void
    {
        $this->assertSame([], (new MenuProvider())->getLinks());
    }
}
