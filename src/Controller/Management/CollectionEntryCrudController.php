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
use c975L\SiteBundle\Entity\CollectionEntry;
use c975L\SiteBundle\Form\VichImageOptions;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;

use function Symfony\Component\Translation\t;

// Generic "title/description/image/link" item, grouped by an arbitrary "group" (e.g. "projects") so this
// one CRUD/table can back several unrelated collections across sites - see CollectionEntrySourceProvider,
// exposing one CollectionSourceProviderInterface source per group to UiBundle's "collection" block.
class CollectionEntryCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return CollectionEntry::class;
    }

    public function createIndexQueryBuilder(...$args): QueryBuilder
    {
        return parent::createIndexQueryBuilder(...$args)
            ->addOrderBy('entity.group', 'ASC')
            ->addOrderBy('entity.position', 'ASC')
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('label.collection_entry', [], 'site'))
            ->setEntityLabelInPlural(t('label.collection_entries', [], 'site'))
            ->setEntityPermission($this->configService->get('site-role-editor'))
            ->setDefaultSort(['group' => 'ASC', 'position' => 'ASC'])
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-editor');

        return $actions
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.edit', [], 'EasyAdminBundle'),
            ))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.delete', [], 'EasyAdminBundle'),
            ))
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            ->setPermission(Action::DETAIL, $role)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')->onlyOnIndex(),

            TextField::new('group')
                ->setLabel(t('label.group', [], 'site'))
                ->setHelp(t('label.collection_entry_group_help', [], 'site')),

            TextField::new('title')
                ->setLabel(t('label.title', [], 'ui')),

            TextareaField::new('description')
                ->setLabel(t('label.description', [], 'ui'))
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
}
