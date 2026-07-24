<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Controller\Management;

use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\Export\ContentExporter;
use c975L\ConfigBundle\Service\Export\ExportFormat;
use c975L\ConfigBundle\Service\Export\TableExporter;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Entity\Redirect;
use c975L\SiteBundle\Form\OgImageType;
use c975L\SiteBundle\Form\Type\PageHealthCheckPanelType;
use c975L\SiteBundle\Form\Type\PageQrCodeType;
use c975L\SiteBundle\Management\PageExportProvider;
use c975L\SiteBundle\Management\PageImportProvider;
use c975L\SiteBundle\Management\SiteBlockOwnerResolver;
use c975L\SiteBundle\Management\TemplateApplier;
use c975L\SiteBundle\Management\TemplateRegistry;
use c975L\SiteBundle\Controller\Management\Trait\BlockMoveRowAttrTrait;
use c975L\SiteBundle\Controller\Management\Trait\UniqueSlugTrait;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Repository\RedirectRepository;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Form\BlockType;
use c975L\UiBundle\Form\Util\CollectionReconciler;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use Endroid\QrCode\Builder\Builder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\ActionGroup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\FormField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

use function Symfony\Component\Translation\t;

class PageCrudController extends AbstractCrudController
{
    use BlockMoveRowAttrTrait;
    use UniqueSlugTrait;

    public function __construct(
        private readonly Security $security,
        private readonly ConfigServiceInterface $configService,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly TranslatorInterface $translator,
        private readonly RedirectRepository $redirectRepository,
        private readonly PageRepository $pageRepository,
        private readonly AdminContextProvider $adminContextProvider,
        private readonly RequestStack $requestStack,
        private readonly SluggerInterface $slugger,
        private readonly Connection $connection,
        private readonly TableExporter $tableExporter,
        private readonly ContentExporter $contentExporter,
        private readonly TemplateRegistry $templateRegistry,
        private readonly TemplateApplier $templateApplier,
        private readonly PageExportProvider $pageExportProvider,
        private readonly CsrfTokenManagerInterface $csrfTokenManager,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Page::class;
    }

    // Removing the very last block also leaves nothing submitted at all for "blocks" (an HTML form can't represent an empty array, only an absent key), which has to be normalized to [] below or Symfony skips add/remove handling entirely for the whole field.
    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $formBuilder = parent::createEditFormBuilder($entityDto, $formOptions, $context);

        $formBuilder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            $page = $event->getForm()->getData();
            if ($page instanceof Page) {
                CollectionReconciler::pruneRemoved(
                    $page->getBlocks(),
                    $data['blocks'] ?? [],
                    static fn (Block $block) => $page->removeBlock($block)
                );
            }

            if (!isset($data['blocks'])) {
                $data['blocks'] = [];
                $event->setData($data);
            }
        });

        return $formBuilder;
    }

    public function configureFields(string $pageName): iterable
    {
        // The "home" page's slug is fixed, it also serves as the site root (see redirect in PageController)
        $entity = $this->adminContextProvider->getContext()?->getEntity()?->getInstance();
        $isHomePage = $entity instanceof Page && 'home' === $entity->getSlug();

        // Trashed pages are always unpublished, no need to show that column in the trash view
        $isTrash = (bool) $this->requestStack->getCurrentRequest()?->query->get('trash');
        $isPublishedField = BooleanField::new('isPublished')
            ->setLabel(t('label.is_published', [], 'site'));
        if ($isTrash) {
            $isPublishedField->hideOnIndex();
        }

        return [
            IdField::new('id')
                ->onlyOnIndex(),

            FormField::addTab(t('label.tab_data', [], 'site'))
                ->hideOnIndex(),

            // Data
            // Confirms with the user before letting them change the title, since it will also change the slug (see updateEntity); handled by the "title-confirm" Stimulus controller (assets/js/title-confirm.js), loaded admin-wide via admin.js; not needed on a new page, since there's no existing slug/redirect to preserve yet and the confirmation modal isn't even rendered on the "new" crud page (only edit/index/detail)
            TextField::new('title')
                ->setLabel(t('label.title', [], 'site'))
                ->setRequired(true)
                ->setFormTypeOption('attr', ($isHomePage || Crud::PAGE_NEW === $pageName) ? [] : [
                    'data-controller' => 'title-confirm',
                    'data-action' => 'focus->title-confirm#confirm click->title-confirm#confirm',
                    'data-title-confirm-message-value' => $this->translator->trans('confirm.title_change', [], 'site'),
                ]),
            SlugField::new('slug')
                ->setLabel(t('label.slug', [], 'site'))
                ->setTargetFieldName('title')
                ->setRequired(true)
                ->setHelp(t('label.slug_help', [], 'site'))
                ->setFormTypeOption('disabled', $isHomePage),

            // Content
            // "data-ai-rephrase" opts this plain textarea into UiBundle's rephrase button (see its
            // block_theme.html.twig's textarea_widget) - off by default there since a plain textarea is
            // also used for non-prose values (e.g. ConfigBundle's JSON config values) that must never get it
            TextareaField::new('summarySocialNetwork')
                ->setLabel(t('label.summary_social_network', [], 'site'))
                ->setHelp(t('label.summary_social_network_help', [], 'site'))
                ->setFormTypeOption('attr', ['data-ai-rephrase' => 'true'])
                ->hideOnIndex(),
            $isPublishedField,

            // Sitemaps
            ChoiceField::new('changeFrequency')
                ->setLabel(t('label.change_frequency', [], 'site'))
                ->setHelp(t('label.change_frequency_help', [], 'site'))
                ->setTranslatableChoices([
                    'always' => t('label.always', [], 'site'),
                    'hourly' => t('label.hourly', [], 'site'),
                    'daily' => t('label.daily', [], 'site'),
                    'weekly' => t('label.weekly', [], 'site'),
                    'monthly' => t('label.monthly', [], 'site'),
                    'yearly' => t('label.yearly', [], 'site'),
                    'never' => t('label.never', [], 'site'),
                ])
                ->setRequired(true)
                ->hideOnIndex(),
            IntegerField::new('priority')
                ->setLabel(t('label.priority', [], 'site'))
                ->setHelp(t('label.priority_help', [], 'site'))
                ->setFormTypeOption('attr', ['min' => 0, 'max' => 10])
                ->setRequired(true)
                ->hideOnIndex(),

            // SEO
            // TextField, not Field: "ogImage" is a real Doctrine ManyToOne (to Media), so a plain Field::new() gets silently rebuilt by EasyAdmin into an AssociationField, which then force-injects EntityType-style "class"/"query_builder" form options regardless of the custom setFormType() below - and OgImageType (a plain AbstractType) doesn't declare those options, so the form crashes. TextField isn't auto-guessed as an association, so this injection never happens; setFormType() fully takes over as intended.
            TextField::new('ogImage')
                ->setLabel(t('label.og_image', [], 'site'))
                ->setHelp(t('label.og_image_help', [], 'site'))
                ->setFormType(OgImageType::class)
                ->setFormTypeOption('required', false)
                ->hideOnIndex(),

            // Blocks
            // row_attr markers read by ea-sortable.js to allow dragging an already-saved Block into a
            // container present on this same page (or back out to top-level) - see BlockMoveController.
            CollectionField::new('blocks')
                ->setLabel(t('label.blocks', [], 'ui'))
                ->setEntryType(BlockType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                ->setFormTypeOption('entry_options.context', 'page')
                ->setFormTypeOption('row_attr', $this->blockMoveRowAttr(SiteBlockOwnerResolver::TYPE_PAGE, $entity instanceof Page ? $entity->getId() : null))
                ->hideOnIndex(),

            // Dates
            DateTimeField::new('creation')
                ->setLabel(t('label.creation', [], 'site'))
                ->setFormTypeOption('disabled', 'disabled')
                ->onlyOnDetail(),
            DateTimeField::new('modification')
                ->setLabel(t('label.modification', [], 'site'))
                ->setFormTypeOption('disabled', 'disabled')
                ->onlyOnDetail(),

            // QR code - needs a saved entity id, and previously only ever rendered on the edit page anyway (a separate template, @c975LSite/management/page_crud_new.html.twig, is used for "new")
            Field::new('qrcode', false)
                ->setFormType(PageQrCodeType::class)
                ->onlyWhenUpdating(),

            // Health check
            // Only on edit: a page has to exist (and be checked at least once by c975l:health-check:run) before there's anything to show here - see PageHealthCheckExtension/PageHealthCheckPanelType/page_crud_form_theme.html.twig
            FormField::addTab(t('label.tab_health_check', [], 'site'))
                ->hideOnIndex()
                ->onlyWhenUpdating(),
            Field::new('healthCheck', false)
                ->setFormType(PageHealthCheckPanelType::class)
                ->onlyWhenUpdating(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-editor');

        // Toggles between "go to trash" and "back to pages", depending on where we currently are
        $isTrash = (bool) $this->requestStack->getCurrentRequest()?->query->get('trash');
        $trashAction = $isTrash
            ? Action::new('trash', t('label.pages', [], 'site'), 'fa fa-file')
                ->linkToUrl(fn () => $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::INDEX)
                    ->unset('trash')
                    ->generateUrl())
            : Action::new('trash', t('action.trash', [], 'site'), 'fa fa-trash-alt')
                ->linkToUrl(fn () => $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::INDEX)
                    ->set('trash', 1)
                    ->generateUrl());
        $trashAction
            ->createAsGlobalAction()
            ->addCssClass('btn btn-secondary');

        // Permanently removes the page, only shown once already in the trash askConfirmation() reuses EasyAdmin's own confirmation modal (the same one shown for "move to trash") instead of a native confirm() - keeps the UI consistent
        $deletePermanentlyAction = Action::new('deletePermanently', t('action.delete_permanently', [], 'site'), 'fa fa-trash')
            ->linkToCrudAction('deletePermanently')
            ->displayIf(static fn (Page $page): bool => $page->isDeleted())
            ->askConfirmation(t('confirm.delete_permanently', [], 'site'))
            ->asDangerAction()
            ->addCssClass('btn btn-danger');

        // Restores a page out of the trash, only shown once already in the trash
        $restoreAction = Action::new('restore', t('action.restore', [], 'site'), 'fa fa-trash-restore')
            ->linkToCrudAction('restore')
            ->displayIf(static fn (Page $page): bool => $page->isDeleted())
            ->addCssClass('btn btn-secondary');

        // Opens the published page on the public site, in a new tab - hidden for unpublished/trashed pages Split from the preview action (rather than one action with a dynamic label) so each keeps its own icon - the only way to tell them apart once icon-only on the index
        $viewOnSiteAction = Action::new('viewOnSite', t('action.view_on_site', [], 'site'), 'fa fa-external-link-alt')
            ->linkToUrl(fn (Page $page) => $this->pagePath($page))
            ->setHtmlAttributes(['target' => '_blank'])
            ->displayIf(static fn (Page $page): bool => $page->isPublished() && !$page->isDeleted())
            ->addCssClass('btn btn-secondary');

        // Opens an admin-only preview of an unpublished page (works even though it isn't public yet), in a new tab
        $previewAction = Action::new('preview', t('action.preview', [], 'site'), 'fa fa-eye')
            ->linkToUrl(fn (Page $page) => $this->pagePath($page))
            ->setHtmlAttributes(['target' => '_blank'])
            ->displayIf(static fn (Page $page): bool => !$page->isPublished() && !$page->isDeleted())
            ->addCssClass('btn btn-secondary');

        // Duplicates the page and all its content (blocks, medias) into a new, unpublished page - saved immediately (see duplicate()), not deferred to a form submit like block duplication is
        $duplicateAction = Action::new('duplicate', t('action.duplicate', [], 'site'), 'fa fa-copy')
            ->linkToCrudAction('duplicate')
            ->displayIf(static fn (Page $page): bool => !$page->isDeleted())
            ->askConfirmation(t('confirm.duplicate', [], 'site'))
            ->addCssClass('btn btn-secondary');

        // Publishes this (non-deleted) page in place of another one, picked from this dropdown - one sub-action per existing other page, same pattern as $templatesGroup below. No longer requires having gone through applyTemplate()'s getReplaces() pre-fill: that field is now only a convenience default (see publishAsReplacement()), the actual target is always the id carried by the link
        // Only queried/built on the edit screen (the only place this group is ever added, see below) - skips a full "every non-deleted page" query and one Action/closure per page on every index/detail render, where the dropdown couldn't be shown anyway
        // Reads the request's crudAction attribute directly rather than via adminContextProvider->getContext(): the AdminContext is only attached to the request by AdminRouterSubscriber AFTER configureActions() runs, so getContext() is always null here. It's a request *attribute*, not a query param - Symfony's router merges it in from the matched route's defaults before EasyAdmin's own subscriber even runs, regardless of whether the URL is query-string or pretty-path based
        $isEditPage = Crud::PAGE_EDIT === $this->requestStack->getCurrentRequest()?->attributes->get(EA::CRUD_ACTION);
        $replaceableTargets = $isEditPage
            ? $this->pageRepository->createQueryBuilder('p')
                ->andWhere('p.isDeleted = :deleted')
                ->setParameter('deleted', false)
                ->getQuery()
                ->getResult()
            : [];
        $publishAsReplacementSubActions = [];
        foreach ($replaceableTargets as $target) {
            $targetId = $target->getId();
            $subActionName = 'publishAsReplacement_' . $targetId;
            $publishAsReplacementSubActions[] = Action::new($subActionName, $target->getTitle())
                ->linkToUrl(fn (Page $page) => $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction('publishAsReplacement')
                    ->setEntityId($page->getId())
                    ->set('replaces', $targetId)
                    ->generateUrl())
                ->displayIf(static fn (Page $page): bool => $page->getId() !== $targetId)
                ->askConfirmation($this->translator->trans('confirm.publish_as_replacement', ['%title%' => $target->getTitle()], 'site'));
            $actions->setPermission($subActionName, $role);
        }

        // Edit screen only, not among the row/detail inline actions - it's a rarer, deliberate act (swaps a live page out), warranting its own visual weight rather than sitting next to preview/duplicate. asWarningActionGroup() (not a raw addCssClass('btn-warning'), which would just sit alongside the group's own default "btn-secondary" and lose out to it in the cascade) flags that weight without implying danger the way asDangerActionGroup()'s red would. Not built/added at all when there's no other page to offer - an EasyAdmin ActionGroup can't be added with zero actions
        if ([] !== $publishAsReplacementSubActions) {
            $publishAsReplacementGroup = array_reduce(
                $publishAsReplacementSubActions,
                static fn (ActionGroup $group, Action $subAction): ActionGroup => $group->addAction($subAction),
                ActionGroup::new('publishAsReplacement', t('action.publish_as_replacement', [], 'site'), 'fa fa-exchange-alt')
                    ->displayIf(static fn (Page $page): bool => !$page->isDeleted())
                    ->asWarningActionGroup()
            );
            $actions->add(Crud::PAGE_EDIT, $publishAsReplacementGroup);
        }

        $exportGroup = ActionGroup::new('export', t('label.export', [], 'site'), 'fa fa-download')
            ->createAsGlobalActionGroup()
            ->addAction(Action::new('exportSql', 'SQL')->linkToCrudAction('exportSql'))
            ->addAction(Action::new('exportCsv', 'CSV')->linkToCrudAction('exportCsv'))
            ->addAction(Action::new('exportJson', 'JSON')->linkToCrudAction('exportJson'))
        ;

        // Lets the admin back out of a create/edit without saving - mirrors EasyAdmin's own built-in actions (linkToCrudAction targeting INDEX, same as Action::INDEX itself)
        $cancelAction = Action::new('cancel', $this->translator->trans('action.cancel', [], 'EasyAdminBundle'), 'fa fa-times')
            ->linkToCrudAction(Action::INDEX)
            ->addCssClass('btn btn-secondary');

        // Exports the checked pages with their Blocks (see exportSelection()/PageImportProvider) as a zip, meant to be re-uploaded elsewhere via ConfigBundle's ContentImportController - restricted to site-role-admin since it's a heavier/less common action than the regular editor permissions
        $exportSelectionAction = Action::new('exportSelection', t('action.export_selection', [], 'site'), 'fa fa-file-export')
            ->createAsBatchAction()
            ->linkToCrudAction('exportSelection');

        // Adds the Blocks of a shipped template (config/templates/*.json) to the page being edited - one action per template, only shown once at least one is registered
        $templates = $this->templateRegistry->all();
        $templatesGroup = [] !== $templates
            ? ActionGroup::new('templates', t('label.templates', [], 'site'), 'fa fa-th-large')
            : null;
        foreach ($templates as $id => $template) {
            // 'label' belongs to whichever bundle contributed the template (see TemplateProviderInterface) - 'site' is only the fallback for a provider that hasn't declared one
            $domain = $template['domain'] ?? 'site';

            $actionName = 'applyTemplate_' . $id;
            $templatesGroup?->addAction(
                Action::new($actionName, $this->translator->trans($template['label'], [], $domain))
                    ->linkToUrl(fn (Page $page) => $this->adminUrlGenerator
                        ->setController(self::class)
                        ->setAction('applyTemplate')
                        ->setEntityId($page->getId())
                        ->set('template', $id)
                        ->generateUrl())
                    ->askConfirmation(t('confirm.apply_template', [], 'site'))
            );
            $actions->setPermission($actionName, $role);
        }
        if (null !== $templatesGroup) {
            $actions->add(Crud::PAGE_EDIT, $templatesGroup);
        }

        $actions->add(Crud::PAGE_INDEX, $exportSelectionAction);
        $actions->setPermission('exportSelection', $this->configService->get('site-role-admin'));

        return $actions
            ->add(Crud::PAGE_INDEX, $trashAction)
            ->add(Crud::PAGE_INDEX, $restoreAction)
            ->add(Crud::PAGE_INDEX, $deletePermanentlyAction)
            ->add(Crud::PAGE_INDEX, $viewOnSiteAction)
            ->add(Crud::PAGE_INDEX, $previewAction)
            ->add(Crud::PAGE_INDEX, $duplicateAction)
            ->add(Crud::PAGE_INDEX, $exportGroup)
            ->add(Crud::PAGE_DETAIL, $restoreAction)
            ->add(Crud::PAGE_DETAIL, $deletePermanentlyAction)
            ->add(Crud::PAGE_DETAIL, $viewOnSiteAction)
            ->add(Crud::PAGE_DETAIL, $previewAction)
            ->add(Crud::PAGE_DETAIL, $duplicateAction)
            ->add(Crud::PAGE_EDIT, $viewOnSiteAction)
            ->add(Crud::PAGE_EDIT, $previewAction)
            ->add(Crud::PAGE_EDIT, $duplicateAction)
            ->add(Crud::PAGE_NEW, $cancelAction)
            ->add(Crud::PAGE_EDIT, $cancelAction)
            ->reorder(Crud::PAGE_INDEX, [Action::EDIT, 'viewOnSite', 'preview', 'duplicate'])
            ->reorder(Crud::PAGE_EDIT, ['viewOnSite', 'preview', 'duplicate'])
            ->reorder(Crud::PAGE_DETAIL, ['viewOnSite', 'preview', 'duplicate'])
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action
                    ->setIcon('fa fa-box-archive')
                    ->displayIf(static fn (Page $page): bool => !$page->isDeleted()),
                $this->translator->trans('action.move_to_trash', [], 'site'),
            ))
            ->update(Crud::PAGE_DETAIL, Action::DELETE, static function (Action $action): Action {
                return $action
                    ->setLabel(t('action.move_to_trash', [], 'site'))
                    ->setIcon('fa fa-box-archive')
                    ->displayIf(static fn (Page $page): bool => !$page->isDeleted());
            })
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action->displayIf(static fn (Page $page): bool => !$page->isDeleted()),
                $this->translator->trans('action.edit', [], 'EasyAdminBundle'),
            ))
            ->update(Crud::PAGE_DETAIL, Action::EDIT, static function (Action $action): Action {
                return $action->displayIf(static fn (Page $page): bool => !$page->isDeleted());
            })
            ->update(Crud::PAGE_INDEX, 'viewOnSite', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.view_on_site', [], 'site'),
            ))
            ->update(Crud::PAGE_INDEX, 'preview', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.preview', [], 'site'),
            ))
            ->update(Crud::PAGE_INDEX, 'duplicate', fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.duplicate', [], 'site'),
            ))
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            ->setPermission(Action::DETAIL, $role)
            ->setPermission('trash', $role)
            ->setPermission('restore', $role)
            ->setPermission('deletePermanently', $role)
            ->setPermission('viewOnSite', $role)
            ->setPermission('preview', $role)
            ->setPermission('duplicate', $role)
            ->setPermission('publishAsReplacement', $role)
            ->setPermission('qrcode', $role)
            ->setPermission('exportSql', $role)
            ->setPermission('exportCsv', $role)
            ->setPermission('exportJson', $role)
        ;
    }

    // Only lists non-deleted pages by default, or deleted ones when viewing the trash
    public function createIndexQueryBuilder(
        SearchDto $searchDto,
        EntityDto $entityDto,
        FieldCollection $fields,
        FilterCollection $filters
    ): QueryBuilder {
        $isTrash = (bool) $this->requestStack->getCurrentRequest()?->query->get('trash');

        return parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters)
            ->andWhere('entity.isDeleted = :isDeleted')
            ->setParameter('isDeleted', $isTrash)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityPermission($this->configService->get('site-role-editor'))
            ->overrideTemplate('crud/index', '@c975LSite/management/page_crud_index.html.twig')
            ->overrideTemplate('crud/edit', '@c975LSite/management/page_crud_edit.html.twig')
            ->overrideTemplate('crud/new', '@c975LSite/management/page_crud_new.html.twig')
            ->addFormTheme('@c975LSite/management/page_crud_form_theme.html.twig')
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('title')
            ->add('slug')
            ->add('creation')
        ;
    }

    // Move to trash: marks page as deleted and unpublished, keeps its content (blocks) intact
    public function deleteEntity(EntityManagerInterface $entityManager, mixed $page): void
    {
        $page->setIsDeleted(true);
        $page->setIsPublished(false);
        $page->setModification(new \DateTime());
        $entityManager->flush();
    }

    // Permanently removes the page and its blocks - only reachable once already in the trash
    #[AdminRoute('/{entityId}/delete-permanently')]
    public function deletePermanently(AdminContext $context, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        $page = $context->getEntity()->getInstance();

        // Redirects pointing to this page's slug would otherwise dangle once it's gone
        foreach ($this->redirectRepository->findByToUrl('/pages/' . $page->getSlug()) as $redirect) {
            $entityManager->remove($redirect);
        }

        $entityManager->remove($page);
        $entityManager->flush();

        $this->addFlash('success', $this->translator->trans('flash.page_deleted_permanently', [], 'site'));

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->set('trash', 1)
                ->generateUrl()
        );
    }

    // Restores a page out of the trash - keeps its content untouched. If it was archived by publishAsReplacement(), tries to reclaim its real slug (free unless something else has since taken it), otherwise keeps the technical one and warns the admin to rename it manually.
    #[AdminRoute('/{entityId}/restore')]
    public function restore(AdminContext $context, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        $page = $context->getEntity()->getInstance();

        $archivedSlug = $page->getArchivedSlug();
        if (null !== $archivedSlug) {
            if (null === $this->pageRepository->findOneBy(['slug' => $archivedSlug])) {
                $page->setSlug($archivedSlug);
            } else {
                $this->addFlash('warning', $this->translator->trans('flash.page_restored_slug_taken', ['%slug%' => $archivedSlug], 'site'));
            }
            $page->setArchivedSlug(null);
        }

        $page->setIsDeleted(false);
        $page->setModification(new \DateTime());
        $entityManager->flush();

        $this->addFlash('success', $this->translator->trans('flash.page_restored', [], 'site'));

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->set('trash', 1)
                ->generateUrl()
        );
    }

    // Duplicates the page with all its content (blocks, medias, og-image) into a new page, unpublished and saved immediately - redirects straight to editing the copy
    #[AdminRoute('/{entityId}/duplicate')]
    public function duplicate(AdminContext $context, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        $copy = $this->clonePage($context->getEntity()->getInstance());

        $entityManager->persist($copy);
        $entityManager->flush();

        $this->addFlash('success', $this->translator->trans('flash.page_duplicated', [], 'site'));

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::EDIT)
                ->setEntityId($copy->getId())
                ->generateUrl()
        );
    }

    // Builds (but does not persist) a clone of a page and all its content (blocks, medias, og-image), unpublished - shared by duplicate() and applyTemplate(), so both stay consistent
    private function clonePage(Page $source): Page
    {
        $user = $this->security->getUser();
        $now = new \DateTime();
        $suffix = $this->translator->trans('label.copy_suffix', [], 'site');

        $copy = (new Page())
            ->setTitle($source->getTitle() . ' (' . $suffix . ')')
            ->setSlug($this->uniqueSlug(
                $this->slugger,
                $source->getSlug() . '-' . $suffix,
                fn (string $candidate): bool => null !== $this->pageRepository->findOneBy(['slug' => $candidate])
            ))
            ->setSummarySocialNetwork($source->getSummarySocialNetwork())
            ->setPriority($source->getPriority())
            ->setChangeFrequency($source->getChangeFrequency())
            ->setIsPublished(false)
            ->setCreation($now)
            ->setModification($now);
        if (null !== $user) {
            $copy->setUser($user);
        }

        if (null !== $source->getOgImage()) {
            $copy->setOgImage($this->cloneMedia($source->getOgImage(), $user));
        }

        foreach ($source->getBlocks() as $block) {
            $copy->addBlock($this->cloneBlock($block, $user));
        }

        return $copy;
    }

    // Applies a template's Blocks (kind + example data, in the template's order) to a fresh, unpublished copy of the page being edited - never mutates the live page in place, so this is safe to use on an already-published page (see clonePage()). The admin then edits the copy's pre-filled content and, once happy with it, uses publishAsReplacement() to swap it in for the original. Same idea as ConfigBundle's ThemeCrudController::applyPreset(), but via a copy instead of in place - TemplateApplyCommand (CLI) still applies in place, deliberately, for scripted use.
    #[AdminRoute('/{entityId}/apply-template')]
    public function applyTemplate(AdminContext $context, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        $source = $context->getEntity()->getInstance();
        $template = $this->templateRegistry->get((string) $request->query->get('template'));

        if (null === $template) {
            return $this->redirect(
                $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::EDIT)
                    ->setEntityId($source->getId())
                    ->generateUrl()
            );
        }

        $copy = $this->clonePage($source)->setReplaces($source->getId());
        $this->templateApplier->apply($copy, $template, $this->security->getUser());

        $entityManager->persist($copy);
        $entityManager->flush();

        $this->addFlash('success', $this->translator->trans('flash.template_applied_to_copy', [], 'site'));

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::EDIT)
                ->setEntityId($copy->getId())
                ->generateUrl()
        );
    }

    // Swaps this (unpublished) page in for the one it replaces: the original's slug is archived, this page takes it over and gets published, and the original is moved to trash (recoverable, see restore()). Looked up by id, not slug - the original's slug may have changed since this copy was created (e.g. archived by another draft's own publishAsReplacement()), and an id stays correct regardless. Since the public slug is never held by both rows at once, and never reassigned back to the trashed original, visitors are never routed to a deleted page (no 410) - two separate flushes so the unique constraint on slug is never violated by the swap itself, wrapped in one transaction so a failure between them can't leave neither row holding the live slug. The target's id comes from the "replaces" query param (picked from the $publishAsReplacementGroup dropdown in configureActions()), falling back to getReplaces() for a page created via applyTemplate() that hasn't had its dropdown target overridden.
    #[AdminRoute('/{entityId}/publish-as-replacement')]
    public function publishAsReplacement(AdminContext $context, EntityManagerInterface $entityManager, ?Request $request = null): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        $copy = $context->getEntity()->getInstance();
        $originalId = $request?->query->getInt('replaces') ?: $copy->getReplaces();
        $original = null !== $originalId ? $this->pageRepository->find($originalId) : null;

        // Already archived by another draft's own publishAsReplacement() (non-null archivedSlug, not yet restored) - its current slug is the mangled "-archived" one, not the live one this copy was meant to take over, so treat it the same as "not found" rather than publishing under it
        if (null !== $original && null !== $original->getArchivedSlug()) {
            $original = null;
        }

        // A page can't replace itself - the dropdown's own displayIf() already hides this option, but the route itself must also refuse it (e.g. a stale/crafted "?replaces=<own id>" link), since $original and $copy would then be the very same entity and the two-flush swap below would leave it both published and deleted at once
        if (null !== $original && $original->getId() === $copy->getId()) {
            $original = null;
        }

        if (null === $original) {
            $this->addFlash('danger', $this->translator->trans('flash.page_to_replace_not_found', [], 'site'));

            return $this->redirect(
                $this->adminUrlGenerator
                    ->setController(self::class)
                    ->setAction(Action::EDIT)
                    ->setEntityId($copy->getId())
                    ->generateUrl()
            );
        }

        $originalSlug = $original->getSlug();

        $entityManager->wrapInTransaction(function () use ($entityManager, $original, $copy, $originalSlug): void {
            $original
                ->setArchivedSlug($originalSlug)
                ->setSlug($this->uniqueSlug(
                    $this->slugger,
                    $originalSlug . '-archived',
                    fn (string $candidate): bool => null !== $this->pageRepository->findOneBy(['slug' => $candidate])
                ))
                ->setIsPublished(false)
                ->setIsDeleted(true)
                ->setModification(new \DateTime());
            $entityManager->flush();

            $copy
                ->setSlug($originalSlug)
                ->setIsPublished(true)
                ->setReplaces(null)
                ->setModification(new \DateTime());
            $entityManager->flush();
        });

        $this->addFlash('success', $this->translator->trans('flash.page_replaced', [], 'site'));

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::EDIT)
                ->setEntityId($copy->getId())
                ->generateUrl()
        );
    }

    // Clones a block (kind, data, animation, position) and its medias - used when duplicating a page
    private function cloneBlock(Block $source, mixed $user): Block
    {
        $copy = (new Block())
            ->setKind($source->getKind())
            ->setPosition($source->getPosition())
            ->setData($source->getData())
            ->setAnimation($source->getAnimation());
        if (null !== $user) {
            $copy->setUser($user);
        }

        foreach ($source->getMedias() as $media) {
            $copy->addMedia($this->cloneMedia($media, $user));
        }

        return $copy;
    }

    // Clones a media row, including its physical file - reusing the existing file as the new Media's upload runs it back through Vich's normal pipeline (see UiMediaNamer/VichImageResizeListener), so the copy ends up with its own independent file rather than sharing the source's. Needs Vich's own ReplacingFile, not a plain File: UploadHandler::hasUploadedFile() only triggers the upload for an UploadedFile or a ReplacingFile, silently ignoring a plain File (leaving filename/size/mimeType null) - ReplacingFile exists precisely for "upload this already-on-disk file programmatically". removeReplacedFile defaults to false, so the source file is left untouched.
    private function cloneMedia(Media $source, mixed $user): Media
    {
        $copy = (new Media())
            ->setRole($source->getRole())
            ->setAlt($source->getAlt())
            ->setLabel($source->getLabel())
            ->setWidth($source->getWidth())
            ->setHeight($source->getHeight())
            ->setCssClasses($source->getCssClasses())
            ->setAbove($source->isAbove())
            ->setCredits($source->getCredits())
            ->setRightsReserved($source->isRightsReserved())
            ->setPosition($source->getPosition());
        if (null !== $user) {
            $copy->setUser($user);
        }

        $filename = $source->getFilename();
        if (null !== $filename) {
            $path = $this->getParameter('kernel.project_dir') . '/public/' . $filename;
            if (is_file($path)) {
                $copy->setFile(new ReplacingFile($path));
            }
        }

        return $copy;
    }

    // New page - Builds a unique slug from a base string (slugified), appending -2, -3... on collision
    public function persistEntity(EntityManagerInterface $entityManager, mixed $page): void
    {
        $this->slugifyPage($page);
        $page->setCreation(new \DateTime());
        $page->setModification(new \DateTime());
        $this->setUser($page);

        parent::persistEntity($entityManager, $page);
    }

    // Updated page - Resyncs the slug when the title changes, then creates a redirect from the old slug when it changes
    public function updateEntity(EntityManagerInterface $entityManager, mixed $page): void
    {
        $originalData = $entityManager->getUnitOfWork()->getOriginalEntityData($page);
        $originalSlug = $originalData['slug'] ?? null;
        $originalTitle = $originalData['title'] ?? null;

        // The home page's slug is fixed (see isHomePage in configureFields), its title can change without affecting it
        if ('home' !== $originalSlug && null !== $originalTitle && $originalTitle !== $page->getTitle()) {
            $page->setSlug($page->getTitle());
        }

        $this->slugifyPage($page);

        if (null !== $originalSlug && $originalSlug !== $page->getSlug()) {
            $this->redirectSlugChange($entityManager, $originalSlug, $page->getSlug());
        }

        $page->setModification(new \DateTime());
        $this->setUser($page);

        parent::updateEntity($entityManager, $page);
    }

    // Relative path of the page on the public site: preview link if unpublished, otherwise home or its slug
    private function pagePath(Page $page): string
    {
        return match (true) {
            !$page->isPublished() => $this->generateUrl('page_preview', ['page' => $page->getSlug()]),
            'home' === $page->getSlug() => $this->generateUrl('page_home'),
            default => $this->generateUrl('page_display', ['page' => $page->getSlug()]),
        };
    }

    // Absolute, public-facing URL of the page (site-url + its path), used for the QR code
    private function buildPageUrl(Page $page): string
    {
        return rtrim((string) $this->configService->get('site-url'), '/') . $this->pagePath($page);
    }

    // Normalizes the slug entered by the user (removes accents, spaces, uppercase...)
    private function slugifyPage(Page $page): void
    {
        $slug = $page->getSlug();
        if (null !== $slug) {
            $page->setSlug(strtolower($this->slugger->slug($slug)->toString()));
        }
    }

    // Redirects the old page URL to the new one, reusing an existing redirect if the old slug already had one
    private function redirectSlugChange(EntityManagerInterface $entityManager, string $oldSlug, string $newSlug): void
    {
        $fromPath = '/pages/' . $oldSlug;
        $toUrl = '/pages/' . $newSlug;

        // Removes any existing redirect starting from the new slug, otherwise it would create a redirect loop (e.g. prestations -> prestations-2, then renaming prestations-2 back to prestations)
        $reverseRedirect = $this->redirectRepository->findOneByFromPath($toUrl);
        if (null !== $reverseRedirect) {
            $entityManager->remove($reverseRedirect);
        }

        $redirect = $this->redirectRepository->findOneByFromPath($fromPath)
            ?? (new Redirect())->setFromPath($fromPath);

        $redirect
            ->setToUrl($toUrl)
            ->setPermanent(true);

        $entityManager->persist($redirect);
    }

    // Defines the user for the page
    private function setUser(Page $page): void
    {
        $user = $this->security->getUser();
        if (null !== $user) {
            $page->setUser($user);
        }
    }

    // Generates on the fly the QR code pointing to the page, shown in the edit view (see page_crud_edit.html.twig)
    #[AdminRoute('/{entityId}/qrcode')]
    public function qrcode(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        $page = $context->getEntity()->getInstance();

        $result = (new Builder())->build(
            data: $this->buildPageUrl($page),
            size: 250,
            margin: 10,
        );

        return new Response($result->getString(), Response::HTTP_OK, ['Content-Type' => $result->getMimeType()]);
    }

    #[AdminRoute]
    public function exportSql(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        return $this->tableExporter->export(ExportFormat::Sql, 'site_page', $this->fetchExportRows());
    }

    #[AdminRoute]
    public function exportCsv(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        return $this->tableExporter->export(ExportFormat::Csv, 'site_page', $this->fetchExportRows());
    }

    #[AdminRoute]
    public function exportJson(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        return $this->tableExporter->export(ExportFormat::Json, 'site_page', $this->fetchExportRows());
    }

    private function fetchExportRows(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM `site_page` ORDER BY `id`');
    }

    // Exports the checked pages (title/slug/blocks, Media files bundled in the archive) as a downloadable zip, meant to be re-uploaded elsewhere via ConfigBundle's ContentImportController (see PageImportProvider) - restricted to site-role-admin, see configureActions(). Unlike exportSql/exportCsv/exportJson above (a raw site_page table dump), this walks each Page's actual Blocks so a page can be moved between environments without its ids ever needing to match
    #[AdminRoute]
    public function exportSelection(AdminContext $context, BatchActionDto $batchActionDto): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        if (Page::class !== $batchActionDto->getEntityFqcn()) {
            throw new BadRequestHttpException();
        }

        if (!$this->isCsrfTokenValid('ea-batch-action-exportSelection-' . $batchActionDto->getEntityFqcn(), $batchActionDto->getCsrfToken())) {
            return $this->redirect($this->adminUrlGenerator->setController(self::class)->setAction(Action::INDEX)->generateUrl());
        }

        $pages = $this->pageRepository->findBy(['id' => $batchActionDto->getEntityIds()]);
        $data = $this->pageExportProvider->serialize($pages);

        return $this->contentExporter->export(PageImportProvider::KIND, $data['items'], $data['files']);
    }

}
