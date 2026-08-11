<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Controller\Management\CollectionCrudController;
use c975L\SiteBundle\Controller\Management\MenuCrudController;
use c975L\SiteBundle\Controller\Management\PageCrudController;
use c975L\SiteBundle\Management\MenuProvider;
use PHPUnit\Framework\TestCase;

class MenuProviderTest extends TestCase
{
    // The dashboard section this bundle contributes must carry the 'site' translation domain
    public function testGetMenuSectionReturnsLabelAndDomain(): void
    {
        $section = new MenuProvider()->getMenuSection();

        $this->assertSame('label.management', $section['label']);
        $this->assertSame('site', $section['translation_domain']);
    }

    // Every CRUD entry this bundle contributes to the dashboard - all of them page-related, everything else having moved to the bundle owning it
    public function testGetMenusReturnsEveryContributedControllerEntry(): void
    {
        $menus = new MenuProvider()->getMenus();

        $this->assertSame(PageCrudController::class, $menus['page']['controller']);
        $this->assertSame(MenuCrudController::class, $menus['menu']['controller']);
        $this->assertSame(CollectionCrudController::class, $menus['collection']['controller']);

        foreach (['media', 'form', 'email_template', 'font', 'site_graphic'] as $uiOwned) {
            $this->assertArrayNotHasKey($uiOwned, $menus, $uiOwned . ' belongs to UiBundle\'s own MenuProvider');
        }

        foreach (['user', 'redirect'] as $configOwned) {
            $this->assertArrayNotHasKey($configOwned, $menus, $configOwned . ' belongs to ConfigBundle\'s own MenuProvider');
        }
    }

    // Day-to-day content items stay at the top level; setup-once/occasional-use screens are tucked into MenuBuilder's collapsed "Advanced" submenu (see MenuProviderInterface::getMenus())
    public function testAdvancedTierIsSetOnlyOnSetupOnceScreens(): void
    {
        $menus = new MenuProvider()->getMenus();

        foreach (['page', 'collection'] as $essential) {
            $this->assertArrayNotHasKey('tier', $menus[$essential], $essential . ' should stay essential');
        }

        $this->assertSame('advanced', $menus['menu']['tier'], 'menu should be advanced');
    }

    // No non-CRUD screen of its own any more: the "Legal models" one moved to UiBundle along with the models
    public function testGetLinksContributesNothing(): void
    {
        $this->assertSame([], new MenuProvider()->getLinks());
    }

    // Every entry's 'description' reuses the exact same key as its own crud/index+crud/edit override template's explanatory text (see eg. page_crud_index.html.twig) - one text, not a separate onboarding-only string
    public function testGetMenusDescriptionReusesEachScreensOwnExplanatoryText(): void
    {
        $menus = new MenuProvider()->getMenus();

        $this->assertSame('label.info_page', $menus['page']['description']);
        $this->assertSame('label.info_menu', $menus['menu']['description']);
        $this->assertSame('label.info_collections', $menus['collection']['description']);
    }
}
