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
use c975L\SiteBundle\Entity\Redirect;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

use function Symfony\Component\Translation\t;

class RedirectCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Redirect::class;
    }

    public function configureFields(string $pageName): iterable
    {
        return [
            IdField::new('id')
                ->onlyOnIndex(),

            TextField::new('fromPath')
                ->setLabel(t('label.from_path', [], 'site'))
                ->setHelp(t('label.from_path_help', [], 'site'))
                ->setRequired(true),

            TextField::new('toUrl')
                ->setLabel(t('label.to_url', [], 'site'))
                ->setHelp(t('label.to_url_help', [], 'site'))
                ->setRequired(true),

            BooleanField::new('permanent')
                ->setLabel(t('label.permanent', [], 'site'))
                ->setHelp(t('label.permanent_help', [], 'site')),
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
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('fromPath')
            ->add('toUrl')
        ;
    }
}
