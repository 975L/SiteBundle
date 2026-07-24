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
use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Repository\CollectionGroupRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

// Named, slugified grouping of CollectionItems - creating one here (rather than free-typing a "group" string on the item, as before) makes it a real, browsable entry point: its "Items" action leads into CollectionItemCrudController scoped to it (see ?collectionGroup=<id>). Its slug is what CollectionItemSourceProvider exposes to UiBundle's "Collection" block as a pickable source.
class CollectionCrudController extends AbstractCrudController
{
    use UniqueSlugTrait;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
        private readonly SluggerInterface $slugger,
        private readonly CollectionGroupRepository $collectionGroupRepository,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return CollectionGroup::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('label.collection', [], 'site'))
            ->setEntityLabelInPlural(t('label.collections', [], 'site'))
            ->setEntityPermission($this->configService->get('site-role-editor'))
            ->showEntityActionsInlined()
            ->overrideTemplate('crud/index', '@c975LSite/management/collection_crud_index.html.twig')
            ->overrideTemplate('crud/edit', '@c975LSite/management/collection_crud_edit.html.twig')
            ->overrideTemplate('crud/new', '@c975LSite/management/collection_crud_new.html.twig')
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),

            TextField::new('name')
                ->setLabel(t('label.collection_name', [], 'site'))
                ->setHelp(t('label.collection_name_help', [], 'site')),

            SlugField::new('slug')
                ->setLabel(t('label.slug', [], 'site'))
                ->setTargetFieldName('name')
                ->setRequired(true)
                ->setHelp(t('label.collection_slug_help', [], 'site')),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-editor');

        // Jumps straight into this collection's items - the whole point of creating one first (see CollectionItemCrudController::currentCollectionGroup())
        $itemsAction = Action::new('items', t('label.collection_items', [], 'site'), 'fas fa-list')
            ->linkToUrl(fn (CollectionGroup $collectionGroup) => $this->adminUrlGenerator
                ->setController(CollectionItemCrudController::class)
                ->setAction(Action::INDEX)
                ->set('collectionGroup', $collectionGroup->getId())
                ->generateUrl())
        ;

        // Lets the admin back out of a create/edit without saving - mirrors EasyAdmin's own built-in actions (linkToCrudAction targeting INDEX, same as Action::INDEX itself)
        $cancelAction = Action::new('cancel', $this->translator->trans('action.cancel', [], 'EasyAdminBundle'), 'fa fa-times')
            ->linkToCrudAction(Action::INDEX)
            ->addCssClass('btn btn-secondary');

        return $actions
            ->add(Crud::PAGE_INDEX, $itemsAction)
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
            ->setPermission('items', $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            // Detail adds no information beyond what edit already shows
            ->disable(Action::DETAIL)
        ;
    }

    // New collection
    public function persistEntity(EntityManagerInterface $entityManager, mixed $collectionGroup): void
    {
        $this->slugifyGroup($collectionGroup);

        parent::persistEntity($entityManager, $collectionGroup);
    }

    // Renamed collection - re-slugifies in case the slug was hand-edited into something already taken
    public function updateEntity(EntityManagerInterface $entityManager, mixed $collectionGroup): void
    {
        $this->slugifyGroup($collectionGroup);

        parent::updateEntity($entityManager, $collectionGroup);
    }

    // Normalizes the slug (removes accents, spaces, uppercase...) and appends -2, -3... on collision - unique site-wide, like Page::$slug
    private function slugifyGroup(CollectionGroup $collectionGroup): void
    {
        $slug = $collectionGroup->getSlug();
        if (null === $slug) {
            return;
        }

        $collectionGroup->setSlug($this->uniqueSlug(
            $this->slugger,
            $slug,
            fn (string $candidate): bool => $this->slugCollides($collectionGroup, $candidate)
        ));
    }

    private function slugCollides(CollectionGroup $collectionGroup, string $candidate): bool
    {
        $existing = $this->collectionGroupRepository->findOneBySlug($candidate);

        return null !== $existing && $existing->getId() !== $collectionGroup->getId();
    }
}
