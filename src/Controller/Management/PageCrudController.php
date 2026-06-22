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
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Translation\TranslatableMessage;

#[IsGranted('ROLE_ADMIN')]
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

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->onlyOnIndex(),
            TextField::new('title')
                ->setLabel(new TranslatableMessage('label.title', [], 'site'))
                ->setRequired(true),
            SlugField::new('slug')
                ->setLabel(new TranslatableMessage('label.slug', [], 'site'))
                ->setTargetFieldName('title')
                ->setRequired(true),
            TextareaField::new('description')
                ->setLabel(new TranslatableMessage('label.description', [], 'site'))
                ->hideOnIndex(),
            IntegerField::new('position')
                ->setLabel(new TranslatableMessage('label.position', [], 'site'))
                ->setHelp(new TranslatableMessage('label.position_help', [], 'site')),
            BooleanField::new('isPublished')
                ->setLabel(new TranslatableMessage('label.is_published', [], 'site')),
            DateTimeField::new('creation')
                ->setLabel(new TranslatableMessage('label.creation', [], 'site'))
                ->setFormTypeOption('disabled', 'disabled')
                ->onlyOnDetail(),
            DateTimeField::new('modification')
                ->setLabel(new TranslatableMessage('label.modification', [], 'site'))
                ->setFormTypeOption('disabled', 'disabled')
                ->onlyOnDetail(),
        ];
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->setPermission(Action::NEW, $this->configService->get('site-role-needed'))
            ->setPermission(Action::EDIT, $this->configService->get('site-role-needed'))
            ->setPermission(Action::DELETE, $this->configService->get('site-role-needed'))
            ->setPermission(Action::DETAIL, $this->configService->get('site-role-needed'))
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
