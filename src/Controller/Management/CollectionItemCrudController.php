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
use c975L\SiteBundle\Controller\Management\Trait\UniqueSlugTrait;
use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Form\VichImageOptions;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;

use function Symfony\Component\Translation\t;

// Generic "title/description/image/link" item, grouped by an arbitrary "group" (e.g. "projects") so this one CRUD/table can back several unrelated collections across sites - see CollectionItemSourceProvider, exposing one CollectionSourceProviderInterface source per group to UiBundle's "collection" block.
class CollectionItemCrudController extends AbstractCrudController
{
    use UniqueSlugTrait;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
        private readonly SluggerInterface $slugger,
        private readonly CollectionItemRepository $collectionItemRepository,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return CollectionItem::class;
    }

    // Without a "group" to scope to, shows the intermediate "pick a group" screen instead of EasyAdmin's own grid
    public function index(AdminContext $context): KeyValueStore|Response
    {
        if (!$this->currentGroup()) {
            return $this->render('@c975LSite/management/collection_item_crud_groups.html.twig', [
                'counts' => $this->collectionItemRepository->countsByGroup(),
                'newUrl' => $this->adminUrlGenerator->setController(self::class)->setAction(Action::NEW)->generateUrl(),
                'newLabel' => $this->translator->trans(
                    'action.new',
                    ['%entity_label_singular%' => $this->translator->trans('label.collection_item', [], 'site')],
                    'EasyAdminBundle',
                ),
            ]);
        }

        return parent::index($context);
    }

    public function createIndexQueryBuilder(...$args): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder(...$args)
            ->addOrderBy('entity.position', 'ASC')
        ;

        $group = $this->currentGroup();
        if (null !== $group) {
            $qb->andWhere('entity.group = :group')->setParameter('group', $group);
        }

        return $qb;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('label.collection_item', [], 'site'))
            ->setEntityLabelInPlural(t('label.collection_items', [], 'site'))
            ->setEntityPermission($this->configService->get('site-role-editor'))
            ->setDefaultSort(['position' => 'ASC'])
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', '@c975LSite/management/collection_item_crud_index.html.twig')
            ->overrideTemplate('crud/edit', '@c975LSite/management/collection_item_crud_edit.html.twig')
            ->overrideTemplate('crud/new', '@c975LSite/management/collection_item_crud_new.html.twig')
            // Drag-and-drop reorder (see collection-item-sort.js) only ever sees the rows on the current page - the index is always filtered to a single group (see index()/createIndexQueryBuilder()), 100 is just a safety margin should one group ever grow past that
            ->setPaginatorPageSize(100)
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-editor');

        // Only shown once a group is selected, mirroring PageCrudController's own trash toggle
        $backToGroupsAction = Action::new('groups', t('label.collection_items', [], 'site'), 'fas fa-layer-group')
            ->linkToUrl(fn () => $this->adminUrlGenerator
                ->setController(self::class)
                ->setAction(Action::INDEX)
                ->unset('group')
                ->generateUrl())
            ->createAsGlobalAction()
        ;

        // Lets the admin back out of a create/edit without saving - mirrors EasyAdmin's own built-in actions (linkToCrudAction targeting INDEX, same as Action::INDEX itself)
        $cancelAction = Action::new('cancel', $this->translator->trans('action.cancel', [], 'EasyAdminBundle'), 'fa fa-times')
            ->linkToCrudAction(Action::INDEX)
            ->addCssClass('btn btn-secondary');

        return $actions
            ->add(Crud::PAGE_INDEX, $backToGroupsAction)
            ->add(Crud::PAGE_NEW, $cancelAction)
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
            ->setPermission('groups', $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            // Detail adds no information beyond what edit already shows
            ->disable(Action::DETAIL)
        ;
    }

    // Persists a new drag-and-drop order for one group's items (see collection_item_crud_index.html.twig and assets/js/collection-item-sort.js). The index is already scoped to one group, but the submitted ids are re-checked against $group here rather than trusted as-is.
    #[AdminRoute(path: '/reorder', options: ['methods' => ['POST']])]
    public function reorder(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        $payload = json_decode($request->getContent(), true) ?? [];
        if (!$this->isCsrfTokenValid('collection_item_reorder', $payload['_token'] ?? null)) {
            throw $this->createAccessDeniedException();
        }

        $group = (string) ($payload['group'] ?? '');
        $ids = array_map('intval', (array) ($payload['ids'] ?? []));

        $itemsById = [];
        foreach ($this->collectionItemRepository->findBy(['id' => $ids]) as $item) {
            if ($item->getGroup() !== $group) {
                throw $this->createAccessDeniedException();
            }
            $itemsById[$item->getId()] = $item;
        }

        foreach (array_values($ids) as $position => $id) {
            $itemsById[$id]?->setPosition($position);
        }

        $entityManager->flush();

        return new JsonResponse(['success' => true]);
    }

    public function configureFields(string $pageName): iterable
    {
        $groupField = TextField::new('group')
            ->setLabel(t('label.group', [], 'site'))
            ->setHelp(t('label.collection_item_group_help', [], 'site'))
        ;
        // Prefills the currently browsed group when creating a new item from within it, so an editor doesn't have to retype it by hand (a typo here would silently create a brand-new group instead of joining the existing one)
        if (Crud::PAGE_NEW === $pageName && null !== ($group = $this->currentGroup())) {
            $groupField->setFormTypeOption('data', $group);
        }

        return [
            IdField::new('id')->onlyOnIndex(),

            $groupField,

            TextField::new('title')
                ->setLabel(t('label.title', [], 'ui')),

            SlugField::new('slug')
                ->setLabel(t('label.slug', [], 'site'))
                ->setTargetFieldName('title')
                ->setRequired(true)
                ->setHelp(t('label.collection_item_slug_help', [], 'site')),

            // "data-ai-rephrase" opts this plain textarea into UiBundle's rephrase button (see its
            // block_theme.html.twig's textarea_widget) - off by default there since a plain textarea is
            // also used for non-prose values (e.g. ConfigBundle's JSON config values) that must never get it
            TextareaField::new('description')
                ->setLabel(t('label.description', [], 'ui'))
                ->setFormTypeOption('attr', ['data-ai-rephrase' => 'true'])
                ->hideOnIndex(),

            TextField::new('url')
                ->setLabel(t('label.url', [], 'ui'))
                ->hideOnIndex(),

            IntegerField::new('position')
                ->setLabel(t('label.position', [], 'site'))
                ->setFormTypeOption('attr', ['class' => 'ui-sort-position']),

            Field::new('file')
                ->setLabel(t('label.image', [], 'site'))
                ->setFormType(VichImageType::class)
                ->setFormTypeOptions(VichImageOptions::default())
                ->onlyOnForms(),

            TextField::new('filename')
                ->setLabel(t('label.image', [], 'site'))
                ->onlyOnIndex(),
        ];
    }

    // New item
    public function persistEntity(EntityManagerInterface $entityManager, mixed $collectionItem): void
    {
        $this->slugifyItem($collectionItem);

        parent::persistEntity($entityManager, $collectionItem);
    }

    // Updated item - re-slugifies in case the slug was hand-edited into something colliding within its group
    public function updateEntity(EntityManagerInterface $entityManager, mixed $collectionItem): void
    {
        $this->slugifyItem($collectionItem);

        parent::updateEntity($entityManager, $collectionItem);
    }

    // Normalizes the slug (removes accents, spaces, uppercase...) and appends -2, -3... on collision - scoped to the item's own "group", unlike Page::$slug which is unique site-wide
    private function slugifyItem(CollectionItem $collectionItem): void
    {
        $slug = $collectionItem->getSlug();
        if (null === $slug) {
            return;
        }

        $collectionItem->setSlug($this->uniqueSlug(
            $this->slugger,
            $slug,
            fn (string $candidate): bool => $this->slugCollides($collectionItem, $candidate)
        ));
    }

    private function slugCollides(CollectionItem $collectionItem, string $candidate): bool
    {
        $existing = $this->collectionItemRepository->findOneByGroupAndSlug(
            (string) $collectionItem->getGroup(),
            $candidate
        );

        return null !== $existing && $existing->getId() !== $collectionItem->getId();
    }

    private function currentGroup(): ?string
    {
        $group = $this->requestStack->getCurrentRequest()?->query->get('group');

        return \is_string($group) && '' !== $group ? $group : null;
    }
}
