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
use c975L\SiteBundle\Controller\Management\MenuCrudController;
use c975L\SiteBundle\Controller\Management\PageCrudController;
use c975L\SiteBundle\Controller\Management\RedirectCrudController;
use c975L\SiteBundle\Controller\Management\SiteGraphicCrudController;
use c975L\SiteBundle\Controller\Management\UserCrudController;
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
            ],
            'redirect' => [
                'controller' => RedirectCrudController::class,
                'label' => 'label.redirects',
                'translation_domain' => 'site',
                'icon' => 'fas fa-arrow-right',
            ],
            'user' => [
                'controller' => UserCrudController::class,
                'label' => 'label.users',
                'translation_domain' => 'site',
                'icon' => 'fas fa-users',
            ],
            'site_graphic' => [
                'controller' => SiteGraphicCrudController::class,
                'label' => 'label.site_graphics',
                'translation_domain' => 'site',
                'icon' => 'fas fa-image',
            ],
            'menu' => [
                'controller' => MenuCrudController::class,
                'label' => 'label.menus',
                'translation_domain' => 'site',
                'icon' => 'fas fa-bars',
            ],
            // Lives in UiBundle (Media is defined there), but UiBundle can't register its own menu
            // entry: ConfigBundle already depends on UiBundle, so the reverse would be circular.
            // SiteBundle depends on both, so it's the natural place to wire this menu item.
            'media' => [
                'controller' => MediaCrudController::class,
                'label' => 'label.media_library',
                'translation_domain' => 'ui',
                'icon' => 'fas fa-photo-film',
            ],
        ];
    }

    public function getLinks(): array
    {
        return [];
    }
}
