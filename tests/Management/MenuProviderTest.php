<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Controller\Management\CollectionCrudController;
use c975L\SiteBundle\Controller\Management\MenuCrudController;
use c975L\SiteBundle\Controller\Management\PageCrudController;
use c975L\SiteBundle\Management\MenuProvider;
use PHPUnit\Framework\TestCase;

class MenuProviderTest extends TestCase
{
    // Answers the editor key each entry names, the bar its own screen states
    private function createProvider(): MenuProvider
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            static fn (string $key) => 'site-role-editor' === $key ? 'ROLE_EDITOR' : null
        );

        return new MenuProvider($configService);
    }

    // What a site publishes is the editor's: without the key the entry takes the admin default and goes missing from their sidebar, with the tour step that walks to it (see MenuProviderInterface::getMenus())
    public function testEveryEntryNamesTheEditorBarItsOwnScreenStates(): void
    {
        $menus = $this->createProvider()->getMenus();

        foreach (['page', 'menu', 'collection'] as $slug) {
            $this->assertSame('ROLE_EDITOR', $menus[$slug]['role'], sprintf('The "%s" entry does not name the bar its own crud states', $slug));
        }
    }

    // The dashboard section this bundle contributes must carry the 'site' translation domain
    public function testGetMenuSectionReturnsLabelAndDomain(): void
    {
        $section = $this->createProvider()->getMenuSection();

        $this->assertSame('label.management', $section['label']);
        $this->assertSame('site', $section['translation_domain']);
    }

    // Every CRUD entry this bundle contributes to the dashboard - all of them page-related, everything else having moved to the bundle owning it
    public function testGetMenusReturnsEveryContributedControllerEntry(): void
    {
        $menus = $this->createProvider()->getMenus();

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
        $menus = $this->createProvider()->getMenus();

        foreach (['page', 'collection'] as $essential) {
            $this->assertArrayNotHasKey('tier', $menus[$essential], $essential . ' should stay essential');
        }

        $this->assertSame('advanced', $menus['menu']['tier'], 'menu should be advanced');
    }

    // No non-CRUD screen of its own any more: the "Legal models" one moved to UiBundle along with the models
    public function testGetLinksContributesNothing(): void
    {
        $this->assertSame([], $this->createProvider()->getLinks());
    }

    // Every entry's 'description' reuses the exact same key as its own crud/index+crud/edit override template's explanatory text (see eg. page_crud_index.html.twig) - one text, not a separate onboarding-only string
    public function testGetMenusDescriptionReusesEachScreensOwnExplanatoryText(): void
    {
        $menus = $this->createProvider()->getMenus();

        $this->assertSame('label.info_page', $menus['page']['description']);
        $this->assertSame('label.info_menu', $menus['menu']['description']);
        $this->assertSame('label.info_collections', $menus['collection']['description']);
    }
}
