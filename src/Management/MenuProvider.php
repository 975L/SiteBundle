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
use c975L\ConfigBundle\Security\Voter\BackOfficeAccessVoter;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Controller\Management\CollectionCrudController;
use c975L\SiteBundle\Controller\Management\MenuCrudController;
use c975L\SiteBundle\Controller\Management\PageCrudController;

class MenuProvider implements MenuProviderInterface
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public function getMenuSection(): array
    {
        return [
            'label' => 'label.management',
            'translation_domain' => 'site',
            'icon' => 'fas fa-sliders',
        ];
    }

    public function getMenus(): array
    {
        return [
            'page' => [
                'controller' => PageCrudController::class,
                'label' => 'label.pages',
                'narration' => 'narration.pages',
                'translation_domain' => 'site',
                'icon' => 'fas fa-file',
                // Same key as page_crud_index.html.twig/page_crud_edit.html.twig's own explanatory text - one text, reused, not a separate onboarding-only string (see MenuProviderInterface::getMenus())
                // The bar PageCrudController sets on its own index - what a site publishes is the editor's, and this is the screen they live in
                'role' => $this->configService->get('site-role-editor'),
                'description' => 'label.info_page',
            ],
            'menu' => [
                'controller' => MenuCrudController::class,
                'label' => 'label.menus',
                'narration' => 'narration.menus',
                'translation_domain' => 'site',
                'icon' => 'fas fa-bars',
                // The bar MenuCrudController sets on its own index - where a published page is put is the same hand's work
                'role' => $this->configService->get('site-role-editor'),
                'description' => 'label.info_menu',
            ],
            'collection' => [
                'controller' => CollectionCrudController::class,
                'label' => 'label.collections',
                'narration' => 'narration.collections',
                'translation_domain' => 'site',
                'icon' => 'fas fa-layer-group',
                // The bar CollectionCrudController sets on its own index
                'role' => $this->configService->get('site-role-editor'),
                'description' => 'label.info_collections',
            ],
        ];
    }

    // The guided projects' films, otherwise only reached through a project's "Watch the film" - the "Legal models" screen this used to declare moved to UiBundle along with the models themselves
    public function getLinks(): array
    {
        return [
            'tutorial_films' => [
                'name' => 'management_tutorial_films',
                'label' => 'label.tutorial_films',
                'narration' => 'narration.tutorial_films',
                'translation_domain' => 'site',
                'icon' => 'fas fa-film',
                // Same gate as TutorialFilmController::index()
                'role' => BackOfficeAccessVoter::ACCESS,
                // Watched once or twice while learning the back office, then left alone
                'tier' => 'advanced',
                'description' => 'label.info_tutorial_films',
            ],
        ];
    }
}
