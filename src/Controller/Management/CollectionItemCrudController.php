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
use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Repository\CollectionGroupRepository;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use c975L\UiBundle\Form\VichImageOptions;
use c975L\UiBundle\Service\UniqueSlug;
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

// Generic "title/description/image/link" item, belonging to a CollectionGroup (e.g. "Projects") so this one CRUD/table can back several unrelated collections across sites - see CollectionItemSourceProvider, exposing one CollectionSourceProviderInterface source per CollectionGroup to UiBundle's "collection" block.
class CollectionItemCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
        private readonly SluggerInterface $slugger,
        private readonly CollectionItemRepository $collectionItemRepository,
        private readonly CollectionGroupRepository $collectionGroupRepository,
        private readonly AdminUrlGenerator $adminUrlGenerator,
        private readonly RequestStack $requestStack,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return CollectionItem::class;
    }

    // Items only ever make sense scoped to one collection - without a resolvable ?collectionGroup=<id>, bounces back to CollectionCrudController's own list instead of showing an ambiguous/empty grid
    #[\Override]
    public function index(AdminContext $context): KeyValueStore | Response
    {
        if (null === $this->currentCollectionGroup()) {
            return $this->redirectToCollectionsList();
        }

        return parent::index($context);
    }

    // Same guard as index() - reachable directly (e.g. a stale bookmark) without ever having browsed into a collection first
    #[\Override]
    public function new(AdminContext $context): KeyValueStore | Response
    {
        if (null === $this->currentCollectionGroup()) {
            return $this->redirectToCollectionsList();
        }

        return parent::new($context);
    }

    // An admin url of this very screen, carrying the collection everything here is scoped by
    private function scopedUrl(string $action): string
    {
        return $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction($action)
            ->set('collectionGroup', $this->currentCollectionGroup()?->getId())
            ->generateUrl();
    }

    private function redirectToCollectionsList(): Response
    {
        return $this->redirect($this->adminUrlGenerator
            ->setController(CollectionCrudController::class)
            ->setAction(Action::INDEX)
            ->generateUrl());
    }

    #[\Override]
    public function createIndexQueryBuilder(...$args): QueryBuilder
    {
        $qb = parent::createIndexQueryBuilder(...$args)
            ->addOrderBy('entity.position', 'ASC')
        ;

        $collectionGroup = $this->currentCollectionGroup();
        if (null !== $collectionGroup) {
            $qb->andWhere('entity.collectionGroup = :collectionGroup')->setParameter('collectionGroup', $collectionGroup);
        }

        return $qb;
    }

    #[\Override]
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
            // Drag-and-drop reorder (see UiBundle's ea-index-sort.js) only ever sees the rows on the current page - the index is always filtered to a single collection (see index()/createIndexQueryBuilder()), 100 is just a safety margin should one collection ever grow past that
            ->setPaginatorPageSize(100)
        ;
    }

    #[\Override]
    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-editor');

        // Back to CollectionCrudController's own list - mirroring PageCrudController's own trash toggle
        $backToCollectionsAction = Action::new('collections', t('label.collections', [], 'site'), 'fas fa-layer-group')
            ->linkToUrl(fn () => $this->adminUrlGenerator
                ->setController(CollectionCrudController::class)
                ->setAction(Action::INDEX)
                ->generateUrl())
            ->createAsGlobalAction()
        ;

        // Lets the admin back out of a create/edit without saving - mirrors EasyAdmin's own built-in actions, save for
        // the collection it has to carry (see $scoped below)
        $cancelAction = Action::new('cancel', $this->translator->trans('action.cancel', [], 'EasyAdminBundle'), 'fa fa-times')
            ->linkToUrl(fn (): string => $this->scopedUrl(Action::INDEX))
            ->addCssClass('btn btn-secondary');

        return $actions
            ->add(Crud::PAGE_INDEX, $backToCollectionsAction)
            ->add(Crud::PAGE_NEW, $cancelAction)
            ->add(Crud::PAGE_EDIT, $cancelAction)
            // The one thing EasyAdmin cannot know: this screen is scoped by a "?collectionGroup=" of its own, which
            // its own url generator does not carry over - so "Nouveau" led to a new() with no collection to attach
            // to, and the guard above bounced the admin back to the list of collections they had just left
            ->update(Crud::PAGE_INDEX, Action::NEW, fn (Action $action) => $action->linkToUrl(
                fn (): string => $this->scopedUrl(Action::NEW),
            ))
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.edit', [], 'EasyAdminBundle'),
            ))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.delete', [], 'EasyAdminBundle'),
            ))
            ->setPermission(Action::INDEX, $role)
            ->setPermission('collections', $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            // Detail adds no information beyond what edit already shows
            ->disable(Action::DETAIL)
        ;
    }

    // Persists a new drag-and-drop order for one collection's items (see collection_item_crud_index.html.twig and UiBundle's assets/js/ea-index-sort.js). The index is already scoped to one collection, but the submitted ids are re-checked against the submitted group here rather than trusted as-is.
    #[AdminRoute(path: '/reorder', options: ['methods' => ['POST']])]
    public function reorder(Request $request, EntityManagerInterface $entityManager): JsonResponse
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-editor'));

        $payload = json_decode($request->getContent(), true) ?? [];
        if (!$this->isCsrfTokenValid('collection_item_reorder', $payload['_token'] ?? null)) {
            throw $this->createAccessDeniedException();
        }

        $ids = array_map(intval(...), (array) ($payload['ids'] ?? []));
        $itemsById = $this->itemsScopedToCollection($ids, (int) ($payload['group'] ?? 0));

        $positions = [];
        foreach (array_values($ids) as $position => $id) {
            if (isset($itemsById[$id])) {
                $itemsById[$id]->setPosition($position);
                $positions[$id] = $position;
            }
        }

        $entityManager->flush();

        // What was persisted, so the index shows the new numbers without a reload
        return new JsonResponse(['positions' => $positions]);
    }

    // The submitted items keyed by id - an id belonging to another collection is refused outright rather than silently reordered
    private function itemsScopedToCollection(array $ids, int $collectionGroupId): array
    {
        $itemsById = [];
        foreach ($this->collectionItemRepository->findBy(['id' => $ids]) as $item) {
            if ($item->getCollectionGroup()?->getId() !== $collectionGroupId) {
                throw $this->createAccessDeniedException();
            }
            $itemsById[$item->getId()] = $item;
        }

        return $itemsById;
    }

    #[\Override]
    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),

            TextField::new('title')
                ->setLabel(t('label.title', [], 'ui')),

            SlugField::new('slug')
                ->setLabel(t('label.slug', [], 'site'))
                ->setTargetFieldName('title')
                ->setRequired(true)
                ->setHelp(t('label.collection_item_slug_help', [], 'site')),

            // Opts this textarea into UiBundle's rephrase button, off by default there for non-prose values
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

    // New item - the collection it belongs to always comes from the browsed context, never from the form itself (see currentCollectionGroup()). Must happen here rather than in persistEntity(), since EasyAdmin validates the form against the entity built by createEntity() before persistEntity() ever runs.
    #[\Override]
    public function createEntity(string $entityFqcn): CollectionItem
    {
        $collectionItem = new CollectionItem();
        $collectionItem->setCollectionGroup($this->currentCollectionGroup());

        return $collectionItem;
    }

    #[\Override]
    public function persistEntity(EntityManagerInterface $entityManager, mixed $collectionItem): void
    {
        $this->slugifyItem($collectionItem);

        parent::persistEntity($entityManager, $collectionItem);
    }

    // Updated item - re-slugifies in case the slug was hand-edited into something colliding within its own collection
    #[\Override]
    public function updateEntity(EntityManagerInterface $entityManager, mixed $collectionItem): void
    {
        $this->slugifyItem($collectionItem);

        parent::updateEntity($entityManager, $collectionItem);
    }

    // Normalizes the slug (removes accents, spaces, uppercase...) and appends -2, -3... on collision - scoped to the item's own collection, unlike Page::$slug which is unique site-wide
    private function slugifyItem(CollectionItem $collectionItem): void
    {
        $slug = $collectionItem->getSlug();
        if (null === $slug) {
            return;
        }

        $collectionItem->setSlug(UniqueSlug::build(
            $this->slugger,
            $slug,
            fn (string $candidate): bool => $this->slugCollides($collectionItem, $candidate)
        ));
    }

    private function slugCollides(CollectionItem $collectionItem, string $candidate): bool
    {
        $collectionGroup = $collectionItem->getCollectionGroup();
        if (null === $collectionGroup) {
            return false;
        }

        $existing = $this->collectionItemRepository->findOneByCollectionGroupAndSlug($collectionGroup, $candidate);

        return null !== $existing && $existing->getId() !== $collectionItem->getId();
    }

    private function currentCollectionGroup(): ?CollectionGroup
    {
        $id = $this->requestStack->getCurrentRequest()?->query->get('collectionGroup');

        return is_numeric($id) ? $this->collectionGroupRepository->find((int) $id) : null;
    }
}
