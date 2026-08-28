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
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Management\SiteBlockOwnerResolver;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Form\BlockType;
use c975L\UiBundle\Form\Util\CollectionReconciler;
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

// Manages the site-wide menus (navbar, footer, email-footer), each owning a single ordered collection of Block rows - see Menu::LOCATION_*. Menu links are the "menu_link" Block kind (MenuLinkType), sortable alongside any other block
class MenuCrudController extends AbstractCrudController
{
    private const array LOCATION_LABELS = [
        Menu::LOCATION_NAVBAR => 'label.navbar',
        Menu::LOCATION_FOOTER => 'label.footer',
        Menu::LOCATION_EMAIL_HEADER => 'label.email_header',
        Menu::LOCATION_EMAIL_FOOTER => 'label.email_footer',
    ];

    // Guards create(), the one action creating a Menu row (see the index template's own buttons)
    private const string CREATE_CSRF_TOKEN = 'site_menu_create';

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly MenuRepository $menuRepository,
        private readonly TranslatorInterface $translator,
        private readonly AdminContextProvider $adminContextProvider,
        private readonly BlockMoveRowAttrBuilder $blockMoveRowAttrBuilder,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Menu::class;
    }

    // Removing the very last block also leaves nothing submitted at all for "blocks" (an HTML form can't represent an empty array, only an absent key), which has to be normalized to [] below or Symfony skips add/remove handling entirely for the field (see PageCrudController for the same trick)
    #[\Override]
    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $formBuilder = parent::createEditFormBuilder($entityDto, $formOptions, $context);

        $formBuilder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            $menu = $event->getForm()->getData();
            if ($menu instanceof Menu) {
                CollectionReconciler::pruneRemoved(
                    $menu->getBlocks(),
                    $data['blocks'] ?? [],
                    static fn (Block $block) => $menu->removeBlock($block)
                );
                $data['blocks'] ??= [];
            }

            $event->setData($data);
        });

        return $formBuilder;
    }

    #[\Override]
    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->overrideTemplate('crud/index', '@c975LSite/management/menu_crud_index.html.twig')
            ->overrideTemplate('crud/edit', '@c975LSite/management/menu_crud_edit.html.twig')
            ->setEntityLabelInSingular(t('label.menu', [], 'site'))
            ->setEntityLabelInPlural(t('label.menus', [], 'site'))
            ->setEntityPermission($this->configService->get('site-role-editor'))
            ->showEntityActionsInlined()
        ;
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-editor');

        // Lets the admin back out of a create/edit without saving - mirrors EasyAdmin's own built-in actions (linkToCrudAction targeting INDEX, same as Action::INDEX itself)
        $cancelAction = Action::new('cancel', $this->translator->trans('action.cancel', [], 'EasyAdminBundle'), 'fa fa-times')
            ->linkToCrudAction(Action::INDEX)
            ->addCssClass('btn btn-secondary');

        return $actions
            ->add(Crud::PAGE_EDIT, $cancelAction)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.edit', [], 'EasyAdminBundle'),
            ))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.delete', [], 'EasyAdminBundle'),
            ))
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            // A menu's only field is its location, so the index offers one creating button per unused one
            ->disable(Action::DETAIL, Action::NEW)
        ;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        $entity = $this->adminContextProvider->getContext()?->getEntity()?->getInstance();
        $isNavbar = $entity instanceof Menu && Menu::LOCATION_NAVBAR === $entity->getLocation();
        $isFooter = $entity instanceof Menu && Menu::LOCATION_FOOTER === $entity->getLocation();

        $locationChoices = [];
        foreach (self::LOCATION_LABELS as $locationSlug => $labelKey) {
            $locationChoices[$locationSlug] = t($labelKey, [], 'site');
        }

        $fields = [
            IdField::new('id')->onlyOnIndex(),

            // Never editable: a row's location is set once by create() and fixed for good, only reassignable by deleting and recreating the row - which is also what keeps the DB-level unique constraint on Menu::$location out of reach
            ChoiceField::new('location')
                ->setLabel(t('label.location', [], 'site'))
                ->setTranslatableChoices($locationChoices)
                ->setFormTypeOption('disabled', true)
                ->setRequired(true),
        ];

        // Only the footer offers it: a navbar lays its items out on its own (stacked below 768px, inlined above, see sass/_menu.scss) and both email menus render as one inline row whatever the site does
        if ($isFooter) {
            // Empty is the placeholder, not a choice: left alone, the layout stays the site theme's own call (the --footer-items-* tokens), which is what every menu saved before this field existed holds
            $fields[] = ChoiceField::new('style')
                ->setLabel(t('label.menu_style', [], 'site'))
                ->setHelp(t('label.menu_style_help', [], 'site'))
                ->setTranslatableChoices([
                    Menu::STYLE_INLINE => t('label.menu_style_inline', [], 'site'),
                    Menu::STYLE_BLOCK => t('label.menu_style_block', [], 'site'),
                ])
                ->setFormTypeOption('placeholder', $this->translator->trans('label.menu_style_theme', [], 'site'))
                ->setRequired(false)
                ->onlyWhenUpdating();
        }

        // row_attr markers read by ea-sortable.js
        $fields[] = CollectionField::new('blocks')
            ->setLabel(t('label.blocks', [], 'ui'))
            // Same reasoning as PageCrudController: CollectionField's "col-md-8 col-xxl-7" default leaves a nested block editor working in 7/12 of the row
            ->setColumns('col-12')
            ->setEntryType(BlockType::class)
            ->allowAdd()
            ->allowDelete()
            ->setFormTypeOption('by_reference', false)
            // A navbar only offers "menu_link", a navigation bar being a plain list of links
            ->setFormTypeOption('entry_options.context', $isNavbar ? BlockRegistry::MENU_NAVBAR_CONTEXT : BlockRegistry::MENU_CONTEXT)
            ->setFormTypeOption('row_attr', $this->blockMoveRowAttrBuilder->build(SiteBlockOwnerResolver::TYPE_MENU, $entity instanceof Menu ? $entity->getId() : null))
            ->onlyWhenUpdating();

        return $fields;
    }

    // Hands the index template the locations no row exists for yet, each rendered as its own "create" button (see menu_crud_index.html.twig) - the whole replacement for the removed "new" form
    #[\Override]
    public function index(AdminContext $context): KeyValueStore | Response
    {
        $responseParameters = parent::index($context);

        if ($responseParameters instanceof KeyValueStore) {
            $responseParameters->set('missing_locations', $this->missingLocations());
        }

        return $responseParameters;
    }

    // Creates the row for the posted location and opens it straight away: a menu's only own field is that location, so asking for it in a form would only repeat what the clicked button already says. Idempotent - a location already created (double submit, stale index in another tab) just opens the existing row instead of hitting the DB-level unique constraint on Menu::$location
    #[AdminRoute(path: '/create', options: ['methods' => ['POST']])]
    public function create(Request $request, EntityManagerInterface $entityManager): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        if (!$this->isCsrfTokenValid(self::CREATE_CSRF_TOKEN, $request->request->getString('_token'))) {
            throw $this->createAccessDeniedException();
        }

        $location = $request->request->getString('location');
        if (!isset(self::LOCATION_LABELS[$location])) {
            throw $this->createNotFoundException();
        }

        $menu = $this->menuRepository->findOneByLocation($location);
        if (null === $menu) {
            $menu = new Menu()->setLocation($location);
            $entityManager->persist($menu);
            $entityManager->flush();
        }

        return $this->redirect(
            $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::EDIT)
                ->setEntityId($menu->getId())
                ->generateUrl()
        );
    }

    // The locations still to create, as "slug => translated label" - empty once all four exist
    private function missingLocations(): array
    {
        $usedLocations = $this->menuRepository->createQueryBuilder('m')
            ->select('m.location')
            ->getQuery()
            ->getSingleColumnResult();

        $missing = [];
        foreach (array_diff_key(self::LOCATION_LABELS, array_flip($usedLocations)) as $locationSlug => $labelKey) {
            $missing[$locationSlug] = $this->translator->trans($labelKey, [], 'site');
        }

        return $missing;
    }
}
