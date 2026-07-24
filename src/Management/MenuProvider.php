<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\MenuProviderInterface;
use c975L\SiteBundle\Controller\Management\CollectionCrudController;
use c975L\SiteBundle\Controller\Management\FontCrudController;
use c975L\SiteBundle\Controller\Management\MenuCrudController;
use c975L\SiteBundle\Controller\Management\PageCrudController;
use c975L\SiteBundle\Controller\Management\RedirectCrudController;
use c975L\SiteBundle\Controller\Management\SiteGraphicCrudController;
use c975L\SiteBundle\Controller\Management\UserCrudController;
use c975L\UiBundle\Controller\Management\EmailTemplateCrudController;
use c975L\UiBundle\Controller\Management\FormCrudController;
use c975L\UiBundle\Controller\Management\MediaCrudController;

class MenuProvider implements MenuProviderInterface
{
    public function getMenuSection(): array
    {
        return [
            'label' => 'label.management',
            'translation_domain' => 'site',
        ];
    }

    public function getMenus(): array
    {
        return [
            'page' => [
                'controller' => PageCrudController::class,
                'label' => 'label.pages',
                'translation_domain' => 'site',
                'icon' => 'fas fa-file',
                // Same key as page_crud_index.html.twig/page_crud_edit.html.twig's own explanatory text - one text, reused, not a separate onboarding-only string (see MenuProviderInterface::getMenus())
                'description' => 'label.info_page',
            ],
            'redirect' => [
                'controller' => RedirectCrudController::class,
                'label' => 'label.redirects',
                'translation_domain' => 'site',
                'icon' => 'fas fa-arrow-right',
                'tier' => 'advanced',
                'description' => 'label.info_redirect',
            ],
            'user' => [
                'controller' => UserCrudController::class,
                'label' => 'label.users',
                'translation_domain' => 'site',
                'icon' => 'fas fa-users',
                'description' => 'label.info_user',
            ],
            'site_graphic' => [
                'controller' => SiteGraphicCrudController::class,
                'label' => 'label.site_graphics',
                'translation_domain' => 'site',
                'icon' => 'fas fa-image',
                'tier' => 'advanced',
                'description' => 'label.info_site_graphic',
            ],
            'menu' => [
                'controller' => MenuCrudController::class,
                'label' => 'label.menus',
                'translation_domain' => 'site',
                'icon' => 'fas fa-bars',
                'tier' => 'advanced',
                'description' => 'label.info_menu',
            ],
            'media' => [
                'controller' => MediaCrudController::class,
                'label' => 'label.media_library',
                'translation_domain' => 'ui',
                'icon' => 'fas fa-photo-film',
                'description' => 'label.info_media',
            ],
            'collection' => [
                'controller' => CollectionCrudController::class,
                'label' => 'label.collections',
                'translation_domain' => 'site',
                'icon' => 'fas fa-layer-group',
                'description' => 'label.info_collections',
            ],
            'font' => [
                'controller' => FontCrudController::class,
                'label' => 'label.fonts',
                'translation_domain' => 'site',
                'icon' => 'fas fa-font',
                'tier' => 'advanced',
                'description' => 'label.info_font',
            ],
            'form' => [
                'controller' => FormCrudController::class,
                'label' => 'label.forms',
                'translation_domain' => 'ui',
                'icon' => 'fas fa-wpforms',
                'tier' => 'advanced',
                'description' => 'label.info_form',
            ],
            'email_template' => [
                'controller' => EmailTemplateCrudController::class,
                'label' => 'label.email_templates',
                'translation_domain' => 'ui',
                'icon' => 'fas fa-envelope-open-text',
                'tier' => 'advanced',
                'description' => 'label.info_email_template',
            ],
        ];
    }

    public function getLinks(): array
    {
        return [];
    }
}
