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

// This bundle's guided projects, running the 2000 block GuidedProjectProviderInterface reserves them - the same docblock stating every other bundle's, so a range is read there rather than recopied here. They are ordered like the sidebar itself reads (Collections, Pages, then the advanced "Menus"), so a project sits where the user finds the screen it walks, and the projects sharing a screen follow each other in the order a page lives: created, made findable, checked, then reworked. Only the opening step of each project carries an url: from there the parcours walks the screen the user is on, each step highlighting the button or the field they are meant to use next - a button they click themselves, which brings the panel back on the very step that pointed at it (see guided-project.js resume())
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
            $this->collectionProject(),
            $this->pageCreationProject(),
            $this->pageSeoProject(),
            $this->pageHealthProject(),
            $this->pageRevisionProject(),
            $this->trashProject(),
            $this->contentExportProject(),
            $this->pageMenuProject(),
            $this->footerProject(),
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
            'order' => 2010,
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
                    // The row marker collection_item_crud_index.html.twig opts each item into, read by UiBundle's ea-index-sort.js - an index row carries no id of its own to point at
                    'label' => 'label.guided_step_collection_order',
                    'description' => 'description.guided_step_collection_order',
                    'highlight' => '[data-reorder-group]',
                ],
                [
                    'label' => 'label.guided_step_collection_display',
                    'description' => 'description.guided_step_collection_display',
                ],
            ],
        ];
    }

    // A page, its content, its publication - what everything else on the site hangs off
    private function pageCreationProject(): array
    {
        return [
            'slug' => 'site-page-creation',
            'label' => 'label.guided_project_page_creation',
            'description' => 'description.guided_project_page_creation',
            'translation_domain' => 'site',
            'order' => 2020,
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
                    'highlight' => '[data-ui-sort-group="block"]',
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

    // A page nobody finds and whose shared link shows nothing is a page nobody reads - the fields saying so are all on the form, and all easy to leave empty
    private function pageSeoProject(): array
    {
        return [
            'slug' => 'site-page-seo',
            'label' => 'label.guided_project_page_seo',
            'description' => 'description.guided_project_page_seo',
            'translation_domain' => 'site',
            'order' => 2030,
            'role' => $this->configService->get('site-role-editor'),
            'steps' => [
                [
                    'label' => 'label.guided_step_page_seo_open',
                    'description' => 'description.guided_step_page_seo_open',
                    'url' => $this->indexUrl(PageCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_page_seo_edit',
                    'description' => 'description.guided_step_page_seo_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_page_seo_slug',
                    'description' => 'description.guided_step_page_seo_slug',
                    'highlight' => '#Page_slug',
                ],
                [
                    'label' => 'label.guided_step_page_seo_summary',
                    'description' => 'description.guided_step_page_seo_summary',
                    'highlight' => '#Page_summarySocialNetwork',
                ],
                [
                    'label' => 'label.guided_step_page_seo_image',
                    'description' => 'description.guided_step_page_seo_image',
                    // The wrapping div OgImageType renders as a compound type, the upload sitting inside it
                    'highlight' => '#Page_ogImage',
                ],
                [
                    'label' => 'label.guided_step_page_seo_indexable',
                    'description' => 'description.guided_step_page_seo_indexable',
                    'highlight' => '#Page_isIndexable',
                ],
                [
                    'label' => 'label.guided_step_page_seo_frequency',
                    'description' => 'description.guided_step_page_seo_frequency',
                    // The step names the priority right below it rather than taking a ninth step of its own: the two are read together, and are the only sitemap hints
                    'highlight' => '#Page_changeFrequency + .ts-wrapper',
                ],
                [
                    'label' => 'label.guided_step_page_seo_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_page_seo_check',
                    'description' => 'description.guided_step_page_seo_check',
                ],
            ],
        ];
    }

    // The two things a page's own screen offers to check it rather than to write it, and which an editor never opens on their own
    private function pageHealthProject(): array
    {
        return [
            'slug' => 'site-page-health',
            'label' => 'label.guided_project_page_health',
            'description' => 'description.guided_project_page_health',
            'translation_domain' => 'site',
            'order' => 2040,
            'role' => $this->configService->get('site-role-editor'),
            'steps' => [
                [
                    'label' => 'label.guided_step_page_health_open',
                    'description' => 'description.guided_step_page_health_open',
                    'url' => $this->indexUrl(PageCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_page_health_edit',
                    'description' => 'description.guided_step_page_health_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    // The QR code comes before the tab below, and not after it: it sits in the "Data" tab, the one already open, so the parcours never sends the user back and forth between the two
                    'label' => 'label.guided_step_page_health_qrcode',
                    'description' => 'description.guided_step_page_health_qrcode',
                    'highlight' => '[data-page-qrcode]',
                ],
                [
                    // The tab link itself, the user opening the pane by clicking it: highlight() only expands the sidebar's own submenu (see guided-ui.js), so a step pointing straight into an inactive tab would outline something nobody sees. Positional rather than by id, EasyAdmin building the id off the translated label - the health check tab is the last one configureFields() declares
                    'label' => 'label.guided_step_page_health_tab',
                    'description' => 'description.guided_step_page_health_tab',
                    'highlight' => '.form-tabs-tablist .nav-item:last-child .nav-link',
                ],
                [
                    // ConfigBundle's own health check table, included as-is by PageHealthCheckPanelType's widget (see page_crud_form_theme.html.twig)
                    'label' => 'label.guided_step_page_health_table',
                    'description' => 'description.guided_step_page_health_table',
                    'highlight' => '[data-controller="health-check-table"]',
                ],
                [
                    // Running the check is ConfigBundle's own screen and its own parcours ("config-health-check"), a site-wide one this step names rather than sends the user into
                    'label' => 'label.guided_step_page_health_run',
                    'description' => 'description.guided_step_page_health_run',
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
            'order' => 2050,
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
                    'highlight' => '[data-ui-sort-group="block"]',
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

    // Deleting a page is never final here, and knowing that is what lets an editor delete one at all
    private function trashProject(): array
    {
        return [
            'slug' => 'site-trash',
            'label' => 'label.guided_project_trash',
            'description' => 'description.guided_project_trash',
            'translation_domain' => 'site',
            'order' => 2060,
            // Deleting only moves a page to the trash, which an editor may do - emptying it or pulling a page back out is "site-role-admin" (see PageCrudController::restore()/deletePermanently()), so the whole parcours takes that role rather than ending on an access-denied page
            'role' => $this->configService->get('site-role-admin'),
            'steps' => [
                [
                    'label' => 'label.guided_step_trash_open',
                    'description' => 'description.guided_step_trash_open',
                    'url' => $this->indexUrl(PageCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_trash_delete',
                    'description' => 'description.guided_step_trash_delete',
                    'highlight' => '.action-delete',
                ],
                [
                    'label' => 'label.guided_step_trash_open_trash',
                    'description' => 'description.guided_step_trash_open_trash',
                    // The one button toggling between the pages and the trash, see PageCrudController::trashAction()
                    'highlight' => '.action-trash',
                ],
                [
                    'label' => 'label.guided_step_trash_restore',
                    'description' => 'description.guided_step_trash_restore',
                    'highlight' => '.action-restore',
                ],
                [
                    'label' => 'label.guided_step_trash_delete_permanently',
                    'description' => 'description.guided_step_trash_delete_permanently',
                    'highlight' => '.action-deletePermanently',
                ],
                [
                    'label' => 'label.guided_step_trash_back',
                    'description' => 'description.guided_step_trash_back',
                ],
            ],
        ];
    }

    // Moving pages to another site, or keeping a copy of them off the server, without touching the database
    private function contentExportProject(): array
    {
        return [
            'slug' => 'site-content-export',
            'label' => 'label.guided_project_content_export',
            'description' => 'description.guided_project_content_export',
            'translation_domain' => 'site',
            'order' => 2070,
            // Same role the "exportSelection" batch action is given, see PageCrudController::configureActions()
            'role' => $this->configService->get('site-role-admin'),
            'steps' => [
                [
                    'label' => 'label.guided_step_content_export_open',
                    'description' => 'description.guided_step_content_export_open',
                    'url' => $this->indexUrl(PageCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_content_export_select',
                    'description' => 'description.guided_step_content_export_select',
                    // EasyAdmin's own "check them all" box, in the index header - the batch actions stay hidden until at least one row is checked, so this step comes before the export button below
                    'highlight' => '#form-batch-checkbox-all',
                ],
                [
                    'label' => 'label.guided_step_content_export_run',
                    'description' => 'description.guided_step_content_export_run',
                    'highlight' => '.action-exportSelection',
                ],
                [
                    // The zip is re-uploaded from ConfigBundle's own import screen, a stricter one this parcours does not walk into (see its README) - the step names it rather than sending the user there
                    'label' => 'label.guided_step_content_export_import',
                    'description' => 'description.guided_step_content_export_import',
                ],
                [
                    // The other export, a global one this time: the pages as a table, in the format the reader needs. An ActionGroup carries no default class, so PageCrudController states this one itself
                    'label' => 'label.guided_step_content_export_formats',
                    'description' => 'description.guided_step_content_export_formats',
                    'highlight' => '.action-export',
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
            'order' => 2080,
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
                    'highlight' => '[data-ui-sort-group="block"]',
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

    // The footer is the one menu laid out from its own edit screen, its items spread over as many lines as it needs
    private function footerProject(): array
    {
        return [
            'slug' => 'site-footer',
            'label' => 'label.guided_project_footer',
            'description' => 'description.guided_project_footer',
            'translation_domain' => 'site',
            'order' => 2090,
            'role' => $this->configService->get('site-role-editor'),
            'steps' => [
                [
                    'label' => 'label.guided_step_footer_open',
                    'description' => 'description.guided_step_footer_open',
                    'url' => $this->indexUrl(MenuCrudController::class),
                ],
                [
                    'label' => 'label.guided_step_footer_create',
                    'description' => 'description.guided_step_footer_create',
                    // Same button as the navbar step above, for the footer's own location - nothing is highlighted on a site already holding the row, which is what the step's description says to do
                    'highlight' => sprintf('button[name="location"][value="%s"]', Menu::LOCATION_FOOTER),
                ],
                [
                    'label' => 'label.guided_step_footer_edit',
                    'description' => 'description.guided_step_footer_edit',
                    'highlight' => '.action-edit',
                ],
                [
                    'label' => 'label.guided_step_footer_items',
                    'description' => 'description.guided_step_footer_items',
                    'highlight' => '[data-ui-sort-group="block"]',
                ],
                [
                    'label' => 'label.guided_step_footer_group',
                    'description' => 'description.guided_step_footer_group',
                ],
                [
                    'label' => 'label.guided_step_footer_style',
                    'description' => 'description.guided_step_footer_style',
                    // Offered on the footer alone, see MenuCrudController::configureFields()
                    'highlight' => '#Menu_style + .ts-wrapper',
                ],
                [
                    'label' => 'label.guided_step_footer_save',
                    'highlight' => '.action-saveAndReturn',
                ],
                [
                    'label' => 'label.guided_step_footer_check',
                    'description' => 'description.guided_step_footer_check',
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
