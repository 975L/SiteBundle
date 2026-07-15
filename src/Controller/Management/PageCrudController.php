<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Controller\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\Export\ExportFormat;
use c975L\ConfigBundle\Service\Export\TableExporter;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Entity\Redirect;
use c975L\SiteBundle\Form\OgImageType;
use c975L\SiteBundle\Management\PageTemplateApplier;
use c975L\SiteBundle\Management\SitePageTemplateProvider;
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
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
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
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

use function Symfony\Component\Translation\t;

class PageCrudController extends AbstractCrudController
{
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
        private readonly SitePageTemplateProvider $pageTemplateProvider,
        private readonly PageTemplateApplier $pageTemplateApplier,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Page::class;
    }

    // Removing the very last block also leaves nothing submitted at all for "blocks" (an HTML form can't
    // represent an empty array, only an absent key), which has to be normalized to [] below or Symfony
    // skips add/remove handling entirely for the whole field.
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

            // Data
            // Confirms with the user before letting them change the title, since it will also change the slug (see updateEntity)
            // Handled by the "titleConfirm" Stimulus controller (assets/js/title-confirm.js), loaded admin-wide via admin.js
            TextField::new('title')
                ->setLabel(t('label.title', [], 'site'))
                ->setRequired(true)
                ->setFormTypeOption('attr', $isHomePage ? [] : [
                    'data-controller' => 'titleConfirm',
                    'data-action' => 'focus->titleConfirm#confirm click->titleConfirm#confirm',
                    'data-title-confirm-message-value' => $this->translator->trans('confirm.title_change', [], 'site'),
                ]),
            SlugField::new('slug')
                ->setLabel(t('label.slug', [], 'site'))
                ->setTargetFieldName('title')
                ->setRequired(true)
                ->setHelp(t('label.slug_help', [], 'site'))
                ->setFormTypeOption('disabled', $isHomePage),

            // Content
            TextareaField::new('summarySocialNetwork')
                ->setLabel(t('label.summary_social_network', [], 'site'))
                ->setHelp(t('label.summary_social_network_help', [], 'site'))
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
            // TextField, not Field: "ogImage" is a real Doctrine ManyToOne (to Media), so a plain
            // Field::new() gets silently rebuilt by EasyAdmin into an AssociationField, which then
            // force-injects EntityType-style "class"/"query_builder" form options regardless of the
            // custom setFormType() below - and OgImageType (a plain AbstractType) doesn't declare
            // those options, so the form crashes. TextField isn't auto-guessed as an association, so
            // this injection never happens; setFormType() fully takes over as intended.
            TextField::new('ogImage')
                ->setLabel(t('label.og_image', [], 'site'))
                ->setHelp(t('label.og_image_help', [], 'site'))
                ->setFormType(OgImageType::class)
                ->setFormTypeOption('required', false)
                ->hideOnIndex(),

            // Blocks
            CollectionField::new('blocks')
                ->setLabel(t('label.blocks', [], 'ui'))
                ->setEntryType(BlockType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                ->setFormTypeOption('entry_options.context', 'page')
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

        // Permanently removes the page, only shown once already in the trash
        // askConfirmation() reuses EasyAdmin's own confirmation modal (the same one shown for "move to
        // trash") instead of a native confirm() - keeps the UI consistent
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

        // Opens the page on the public site if published, or a preview (admin-only, works even unpublished) otherwise
        // In a new tab - hidden for trashed pages (they 410 on the site)
        $viewOnSiteAction = Action::new(
            'viewOnSite',
            static fn (Page $page) => $page->isPublished()
                ? t('action.view_on_site', [], 'site')
                : t('action.preview', [], 'site'),
            'fa fa-external-link-alt'
        )
            ->linkToUrl(fn (Page $page) => $this->pagePath($page))
            ->setHtmlAttributes(['target' => '_blank'])
            ->displayIf(static fn (Page $page): bool => !$page->isDeleted())
            ->addCssClass('btn btn-secondary');

        // Duplicates the page and all its content (blocks, medias) into a new, unpublished page - saved
        // immediately (see duplicate()), not deferred to a form submit like block duplication is
        $duplicateAction = Action::new('duplicate', t('action.duplicate', [], 'site'), 'fa fa-copy')
            ->linkToCrudAction('duplicate')
            ->displayIf(static fn (Page $page): bool => !$page->isDeleted())
            ->askConfirmation(t('confirm.duplicate', [], 'site'))
            ->addCssClass('btn btn-secondary');

        $exportGroup = ActionGroup::new('export', t('label.export', [], 'site'), 'fa fa-download')
            ->createAsGlobalActionGroup()
            ->addAction(Action::new('exportSql', 'SQL')->linkToCrudAction('exportSql'))
            ->addAction(Action::new('exportCsv', 'CSV')->linkToCrudAction('exportCsv'))
            ->addAction(Action::new('exportJson', 'JSON')->linkToCrudAction('exportJson'))
        ;

        // Adds the Blocks of a shipped page-template (config/page-templates/*.json) to the page being
        // edited - one action per template, only shown once at least one is registered
        $templates = $this->pageTemplateProvider->getTemplates();
        $templatesGroup = [] !== $templates
            ? ActionGroup::new('pageTemplates', t('label.page_templates', [], 'site'), 'fa fa-th-large')
            : null;
        foreach ($templates as $id => $template) {
            $actionName = 'applyTemplate_' . $id;
            $templatesGroup?->addAction(
                Action::new($actionName, $this->translator->trans($template['label'], [], 'site'))
                    ->linkToUrl(fn (Page $page) => $this->adminUrlGenerator
                        ->setController(self::class)
                        ->setAction('applyTemplate')
                        ->setEntityId($page->getId())
                        ->set('template', $id)
                        ->generateUrl())
                    ->askConfirmation(t('confirm.apply_page_template', [], 'site'))
            );
            $actions->setPermission($actionName, $role);
        }
        if (null !== $templatesGroup) {
            $actions->add(Crud::PAGE_EDIT, $templatesGroup);
        }

        return $actions
            ->add(Crud::PAGE_INDEX, $trashAction)
            ->add(Crud::PAGE_INDEX, $restoreAction)
            ->add(Crud::PAGE_INDEX, $deletePermanentlyAction)
            ->add(Crud::PAGE_INDEX, $viewOnSiteAction)
            ->add(Crud::PAGE_INDEX, $duplicateAction)
            ->add(Crud::PAGE_INDEX, $exportGroup)
            ->add(Crud::PAGE_DETAIL, $restoreAction)
            ->add(Crud::PAGE_DETAIL, $deletePermanentlyAction)
            ->add(Crud::PAGE_DETAIL, $viewOnSiteAction)
            ->add(Crud::PAGE_DETAIL, $duplicateAction)
            ->add(Crud::PAGE_EDIT, $viewOnSiteAction)
            ->add(Crud::PAGE_EDIT, $duplicateAction)
            ->reorder(Crud::PAGE_INDEX, [Action::EDIT, 'viewOnSite', 'duplicate'])
            ->reorder(Crud::PAGE_EDIT, ['viewOnSite', 'duplicate'])
            ->reorder(Crud::PAGE_DETAIL, ['viewOnSite', 'duplicate'])
            ->update(Crud::PAGE_INDEX, Action::DELETE, static function (Action $action): Action {
                return $action
                    ->setLabel(t('action.move_to_trash', [], 'site'))
                    ->setIcon('fa fa-box-archive')
                    ->displayIf(static fn (Page $page): bool => !$page->isDeleted());
            })
            ->update(Crud::PAGE_DETAIL, Action::DELETE, static function (Action $action): Action {
                return $action
                    ->setLabel(t('action.move_to_trash', [], 'site'))
                    ->setIcon('fa fa-box-archive')
                    ->displayIf(static fn (Page $page): bool => !$page->isDeleted());
            })
            ->update(Crud::PAGE_INDEX, Action::EDIT, static function (Action $action): Action {
                return $action->displayIf(static fn (Page $page): bool => !$page->isDeleted());
            })
            ->update(Crud::PAGE_DETAIL, Action::EDIT, static function (Action $action): Action {
                return $action->displayIf(static fn (Page $page): bool => !$page->isDeleted());
            })
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            ->setPermission(Action::DETAIL, $role)
            ->setPermission('trash', $role)
            ->setPermission('restore', $role)
            ->setPermission('deletePermanently', $role)
            ->setPermission('viewOnSite', $role)
            ->setPermission('duplicate', $role)
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

    // Restores a page out of the trash - keeps its content untouched
    #[AdminRoute('/{entityId}/restore')]
    public function restore(AdminContext $context, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-admin'));

        $page = $context->getEntity()->getInstance();

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

    // Duplicates the page with all its content (blocks, medias, og-image) into a new page, unpublished
    // and saved immediately - redirects straight to editing the copy
    #[AdminRoute('/{entityId}/duplicate')]
    public function duplicate(AdminContext $context, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        $source = $context->getEntity()->getInstance();
        $user = $this->security->getUser();
        $now = new \DateTime();
        $suffix = $this->translator->trans('label.copy_suffix', [], 'site');

        $copy = (new Page())
            ->setTitle($source->getTitle() . ' (' . $suffix . ')')
            ->setSlug($this->uniqueSlug($source->getSlug() . '-' . $suffix))
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

    // Bulk-adds a page-template's Blocks (kind + example data, in the template's order) to the page
    // being edited - the admin then edits the pre-filled content, same idea as ConfigBundle's
    // ThemeCrudController::applyPreset(). Appended after any existing blocks, not replacing them.
    #[AdminRoute('/{entityId}/apply-template')]
    public function applyTemplate(AdminContext $context, Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        $page = $context->getEntity()->getInstance();
        $template = $this->pageTemplateProvider->getTemplate((string) $request->query->get('template'));

        if (null !== $template) {
            $this->pageTemplateApplier->apply($page, $template, $this->security->getUser());

            $entityManager->flush();
            $this->addFlash('success', $this->translator->trans('flash.page_template_applied', [], 'site'));
        }

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::EDIT)
                ->setEntityId($page->getId())
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

    // Clones a media row, including its physical file - reusing the existing file as the new Media's
    // upload runs it back through Vich's normal pipeline (see UiMediaNamer/VichImageResizeListener),
    // so the copy ends up with its own independent file rather than sharing the source's.
    // Needs Vich's own ReplacingFile, not a plain File: UploadHandler::hasUploadedFile() only triggers
    // the upload for an UploadedFile or a ReplacingFile, silently ignoring a plain File (leaving
    // filename/size/mimeType null) - ReplacingFile exists precisely for "upload this already-on-disk
    // file programmatically". removeReplacedFile defaults to false, so the source file is left untouched.
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

    // Builds a unique slug from a base string (slugified), appending -2, -3... on collision
    private function uniqueSlug(string $base): string
    {
        $slug = strtolower($this->slugger->slug($base)->toString());
        $candidate = $slug;
        for ($i = 2; null !== $this->pageRepository->findOneBy(['slug' => $candidate]); $i++) {
            $candidate = $slug . '-' . $i;
        }

        return $candidate;
    }

    // New page
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

        // Removes any existing redirect starting from the new slug, otherwise it would create a redirect loop
        // (e.g. prestations -> prestations-2, then renaming prestations-2 back to prestations)
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
}
