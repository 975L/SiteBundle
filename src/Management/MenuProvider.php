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
use c975L\SiteBundle\Controller\Management\PageCrudController;
use c975L\SiteBundle\Controller\Management\RedirectCrudController;

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
        ];
    }

    public function getLinks(): array
    {
        return [];
    }
}
