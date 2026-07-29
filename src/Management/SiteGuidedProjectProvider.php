<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\GuidedProjectProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Controller\Management\CollectionCrudController;
use c975L\SiteBundle\Controller\Management\MenuCrudController;
use c975L\SiteBundle\Controller\Management\PageCrudController;
use c975L\SiteBundle\Entity\Menu;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// This bundle's guided projects, continuing ConfigBundle's order sequence and picking up at 50, same as SiteEssentialActionProvider. Only the opening step of each project carries an url: from there the parcours walks the screen the user is on, each step highlighting the button or the field they are meant to use next - a button they click themselves, which brings the panel back on the very step that pointed at it (see guided-project.js resume())
class SiteGuidedProjectProvider implements GuidedProjectProviderInterface
{
    public function __construct(
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public function getGuidedProjects(): array
    {
        return [
            $this->pageCreationProject(),
            $this->pageMenuProject(),
            $this->collectionProject(),
            $this->pageRevisionProject(),
        ];
    }

    // The first thing anybody does on a new site: a page, its content, its publication
    private function pageCreationProject(): array
    {
        return [
            'slug' => 'site-page-creation',
            'label' => 'label.guided_project_page_creation',
            'description' => 'description.guided_project_page_creation',
            'translation_domain' => 'site',
            'order' => 50,
            'role' => $this->configService->get('site-role-editor'),
            'steps' => [
                [
                    'label' => 'label.guided_step_page_creation_open',
                    'description' => 'description.guided_step_page_creation_open',
                    'url' => $this->indexUrl(PageCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_page_creation_new',
                    'description' => 'description.guided_step_page_creation_new',
                    'highlight' => '.action-new',
                ],
                [
                    'label' => 'label.guided_step_page_creation_title',
                    'description' => 'description.guided_step_page_creation_title',
                    'highlight' => '#Page_title',
                ],
                [
                    'label' => 'label.guided_step_page_creation_save',
                    'description' => 'description.guided_step_page_creation_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_page_creation_reopen',
                    'description' => 'description.guided_step_page_creation_reopen',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_page_creation_blocks',
                    'description' => 'description.guided_step_page_creation_blocks',
                    'highlight' => '[data-block-collection]',
                ],
                [
                    'label' => 'label.guided_step_page_creation_publish',
                    'description' => 'description.guided_step_page_creation_publish',
                    'highlight' => '#Page_isPublished',
                ],
                [
                    'label' => 'label.guided_step_page_creation_save_again',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_page_creation_view',
                    'description' => 'description.guided_step_page_creation_view',
                    'highlight' => '.action-viewOnSite',
                ],
            ],
        ];
    }

    // A published page nobody can reach from the navbar is a page nobody reads
    private function pageMenuProject(): array
    {
        return [
            'slug' => 'site-page-menu',
            'label' => 'label.guided_project_page_menu',
            'description' => 'description.guided_project_page_menu',
            'translation_domain' => 'site',
            'order' => 60,
            'role' => $this->configService->get('site-role-editor'),
            'steps' => [
                [
                    'label' => 'label.guided_step_page_menu_open',
                    'description' => 'description.guided_step_page_menu_open',
                    'url' => $this->indexUrl(MenuCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_page_menu_create',
                    'description' => 'description.guided_step_page_menu_create',
                    // One create button per location not created yet, so the value is what tells the navbar's apart - and nothing is highlighted on a site already holding it, which is what the step's own description says to do
                    'highlight' => sprintf('button[name="location"][value="%s"]', Menu::LOCATION_NAVBAR),
                ],
                [
                    'label' => 'label.guided_step_page_menu_edit',
                    'description' => 'description.guided_step_page_menu_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_page_menu_add_link',
                    'description' => 'description.guided_step_page_menu_add_link',
                    'highlight' => '[data-block-collection]',
                ],
                [
                    'label' => 'label.guided_step_page_menu_target',
                    'description' => 'description.guided_step_page_menu_target',
                ],
                [
                    'label' => 'label.guided_step_page_menu_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_page_menu_check',
                    'description' => 'description.guided_step_page_menu_check',
                ],
            ],
        ];
    }

    // Repeating content (team, references, events) belongs in a collection, not in as many hand-built pages
    private function collectionProject(): array
    {
        return [
            'slug' => 'site-collection',
            'label' => 'label.guided_project_collection',
            'description' => 'description.guided_project_collection',
            'translation_domain' => 'site',
            'order' => 70,
            'role' => $this->configService->get('site-role-editor'),
            'steps' => [
                [
                    'label' => 'label.guided_step_collection_open',
                    'description' => 'description.guided_step_collection_open',
                    'url' => $this->indexUrl(CollectionCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_collection_new',
                    'highlight' => '.action-new',
                ],
                [
                    'label' => 'label.guided_step_collection_name',
                    'description' => 'description.guided_step_collection_name',
                    'highlight' => '#CollectionGroup_name',
                ],
                [
                    'label' => 'label.guided_step_collection_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_collection_items',
                    'description' => 'description.guided_step_collection_items',
                    'highlight' => '.action-items',
                ],
                [
                    'label' => 'label.guided_step_collection_add_item',
                    'description' => 'description.guided_step_collection_add_item',
                    'highlight' => '.action-new',
                ],
                [
                    'label' => 'label.guided_step_collection_display',
                    'description' => 'description.guided_step_collection_display',
                ],
            ],
        ];
    }

    // Reworking a page that is already online, without ever showing a half-finished version to a visitor
    private function pageRevisionProject(): array
    {
        return [
            'slug' => 'site-page-revision',
            'label' => 'label.guided_project_page_revision',
            'description' => 'description.guided_project_page_revision',
            'translation_domain' => 'site',
            'order' => 80,
            'role' => $this->configService->get('site-role-editor'),
            'steps' => [
                [
                    'label' => 'label.guided_step_page_revision_open',
                    'description' => 'description.guided_step_page_revision_open',
                    'url' => $this->indexUrl(PageCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_page_revision_duplicate',
                    'description' => 'description.guided_step_page_revision_duplicate',
                    'highlight' => '.action-duplicate',
                ],
                [
                    'label' => 'label.guided_step_page_revision_rework',
                    'description' => 'description.guided_step_page_revision_rework',
                    'highlight' => '[data-block-collection]',
                ],
                [
                    'label' => 'label.guided_step_page_revision_preview',
                    'description' => 'description.guided_step_page_revision_preview',
                    'highlight' => '.action-preview',
                ],
                [
                    'label' => 'label.guided_step_page_revision_replace',
                    'description' => 'description.guided_step_page_revision_replace',
                    'highlight' => '.action-publishAsReplacement',
                ],
                [
                    'label' => 'label.guided_step_page_revision_done',
                    'description' => 'description.guided_step_page_revision_done',
                ],
            ],
        ];
    }

    private function indexUrl(string $controllerFqcn): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController($controllerFqcn)
            ->setAction(Action::INDEX)
            ->generateUrl();
    }
}
