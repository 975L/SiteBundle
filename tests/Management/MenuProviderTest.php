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
use c975L\SiteBundle\Controller\Management\LegalModelController;
use c975L\SiteBundle\Controller\Management\MenuCrudController;
use c975L\SiteBundle\Controller\Management\PageCrudController;
use c975L\SiteBundle\Controller\Management\RedirectCrudController;
use c975L\SiteBundle\Controller\Management\SiteGraphicCrudController;
use c975L\SiteBundle\Controller\Management\UserCrudController;
use c975L\SiteBundle\Management\MenuProvider;
use c975L\UiBundle\Controller\Management\EmailTemplateCrudController;
use c975L\UiBundle\Controller\Management\FormCrudController;
use c975L\UiBundle\Controller\Management\MediaCrudController;
use PHPUnit\Framework\TestCase;

class MenuProviderTest extends TestCase
{
    // The dashboard section this bundle contributes must carry the 'site' translation domain
    public function testGetMenuSectionReturnsLabelAndDomain(): void
    {
        $section = $this->provider()->getMenuSection();

        $this->assertSame('label.management', $section['label']);
        $this->assertSame('site', $section['translation_domain']);
    }

    // Every CRUD entry this bundle contributes to the dashboard, including UiBundle's media library (wired here because UiBundle can't register its own menu item without a circular dependency)
    public function testGetMenusReturnsEveryContributedControllerEntry(): void
    {
        $menus = $this->provider()->getMenus();

        $this->assertSame(PageCrudController::class, $menus['page']['controller']);
        $this->assertSame(RedirectCrudController::class, $menus['redirect']['controller']);
        $this->assertSame(UserCrudController::class, $menus['user']['controller']);
        $this->assertSame(SiteGraphicCrudController::class, $menus['site_graphic']['controller']);
        $this->assertSame(MenuCrudController::class, $menus['menu']['controller']);
        $this->assertSame(MediaCrudController::class, $menus['media']['controller']);
        $this->assertSame('ui', $menus['media']['translation_domain']);
        $this->assertSame(CollectionCrudController::class, $menus['collection']['controller']);
        $this->assertSame(FormCrudController::class, $menus['form']['controller']);
        $this->assertSame('ui', $menus['form']['translation_domain']);
        $this->assertSame(EmailTemplateCrudController::class, $menus['email_template']['controller']);
        $this->assertSame('ui', $menus['email_template']['translation_domain']);
    }

    // Day-to-day content items stay at the top level; setup-once/occasional-use screens are tucked into MenuBuilder's collapsed "Advanced" submenu (see MenuProviderInterface::getMenus())
    public function testAdvancedTierIsSetOnlyOnSetupOnceScreens(): void
    {
        $menus = $this->provider()->getMenus();

        foreach (['page', 'user', 'media', 'collection'] as $essential) {
            $this->assertArrayNotHasKey('tier', $menus[$essential], $essential . ' should stay essential');
        }

        foreach (['redirect', 'site_graphic', 'menu', 'font', 'form', 'email_template'] as $advanced) {
            $this->assertSame('advanced', $menus[$advanced]['tier'], $advanced . ' should be advanced');
        }
    }

    // The only non-CRUD screen this provider contributes: customizing a legal model is not an entity CRUD, it
    // edits one block's delta against templates the bundle ships (see LegalModelController)
    public function testGetLinksContributesTheLegalModelsScreen(): void
    {
        $links = $this->provider()->getLinks();

        $this->assertSame(['site_legal_models'], array_keys($links));
        $this->assertSame(LegalModelController::INDEX_ROUTE, $links['site_legal_models']['name']);
        $this->assertSame('site', $links['site_legal_models']['translation_domain']);
        // Gated on the same role the screen itself demands, so it never shows to someone it would 403 on
        $this->assertSame('ROLE_EDITOR', $links['site_legal_models']['role']);
        // Set up once, then revisited rarely - it belongs in the collapsed "Avancé" submenu, not the sidebar's
        // own "Liens" section
        $this->assertSame('advanced', $links['site_legal_models']['tier']);
    }

    // Every entry's 'description' reuses the exact same key as its own crud/index+crud/edit override template's explanatory text (see eg. page_crud_index.html.twig) - one text, not a separate onboarding-only string
    public function testGetMenusDescriptionReusesEachScreensOwnExplanatoryText(): void
    {
        $menus = $this->provider()->getMenus();

        $this->assertSame('label.info_page', $menus['page']['description']);
        $this->assertSame('label.info_redirect', $menus['redirect']['description']);
        $this->assertSame('label.info_user', $menus['user']['description']);
        $this->assertSame('label.info_site_graphic', $menus['site_graphic']['description']);
        $this->assertSame('label.info_menu', $menus['menu']['description']);
        $this->assertSame('label.info_media', $menus['media']['description']);
        $this->assertSame('label.info_collections', $menus['collection']['description']);
        $this->assertSame('label.info_font', $menus['font']['description']);
        $this->assertSame('label.info_form', $menus['form']['description']);
        $this->assertSame('label.info_email_template', $menus['email_template']['description']);
    }

    private function provider(): MenuProvider
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_EDITOR');

        return new MenuProvider($configService);
    }
}
