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
use c975L\SiteBundle\Entity\Page;
use c975L\UiBundle\Form\BlockType;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Bundle\SecurityBundle\Security;

use function Symfony\Component\Translation\t;

class PageCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly Security $security,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Page::class;
    }

    // Add JS for blocks, to handle change of kind
    public function configureAssets(Assets $assets): Assets
    {
        return $assets->addJsFile('@c975l/ui-bundle/js/blocks.js');
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->onlyOnIndex(),

            // Data
            TextField::new('title')
                ->setLabel(t('label.title', [], 'site'))
                ->setRequired(true),
            SlugField::new('slug')
                ->setLabel(t('label.slug', [], 'site'))
                ->setTargetFieldName('title')
                ->setRequired(true),

            // Content
            TextareaField::new('description')
                ->setLabel(t('label.description', [], 'site'))
                ->setHelp(t('label.description_help', [], 'site'))
                ->hideOnIndex(),
            IntegerField::new('position')
                ->setLabel(t('label.position', [], 'site'))
                ->setHelp(t('label.position_help', [], 'site'))
                ->setRequired(true),
            BooleanField::new('isPublished')
                ->setLabel(t('label.is_published', [], 'site')),

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

            // Blocks
            CollectionField::new('blocks')
                ->setLabel(t('label.blocks', [], 'ui'))
                ->setEntryType(BlockType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
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
        $role = $this->configService->get('site-role-needed');

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            ->setPermission(Action::DETAIL, $role)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityPermission($this->configService->get('site-role-needed'))
            ->setDefaultSort(['position' => 'ASC', 'id' => 'DESC'])
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

    // New page
    public function persistEntity(EntityManagerInterface $entityManager, mixed $page): void
    {
        $page->setCreation(new \DateTime());
        $page->setModification(new \DateTime());
        $this->setUser($page);

        parent::persistEntity($entityManager, $page);
    }

    // Updated page - Invalidate cache
    public function updateEntity(EntityManagerInterface $entityManager, mixed $page): void
    {
        $page->setModification(new \DateTime());
        $this->setUser($page);

        parent::updateEntity($entityManager, $page);
    }

    // Defines the user for the page
    private function setUser(Page $page): void
    {
        $user = $this->security->getUser();
        if (null !== $user) {
            $page->setUser($user);
        }
    }
}
