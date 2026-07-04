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
use c975L\SiteBundle\Entity\Redirect;
use Doctrine\DBAL\Connection;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\ActionGroup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\HttpFoundation\Response;

use function Symfony\Component\Translation\t;

class RedirectCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly Connection $connection,
        private readonly TableExporter $tableExporter,
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

        $exportGroup = ActionGroup::new('export', t('label.export', [], 'site'), 'fa fa-download')
            ->createAsGlobalActionGroup()
            ->addAction(Action::new('exportSql', 'SQL')->linkToCrudAction('exportSql'))
            ->addAction(Action::new('exportCsv', 'CSV')->linkToCrudAction('exportCsv'))
            ->addAction(Action::new('exportJson', 'JSON')->linkToCrudAction('exportJson'))
        ;

        return $actions
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $exportGroup)
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            ->setPermission(Action::DETAIL, $role)
            ->setPermission('exportSql', $role)
            ->setPermission('exportCsv', $role)
            ->setPermission('exportJson', $role)
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityPermission($this->configService->get('site-role-needed'))
            ->overrideTemplate('crud/index', '@c975LSite/management/redirect_crud_index.html.twig')
        ;
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add('fromPath')
            ->add('toUrl')
        ;
    }

    #[AdminRoute]
    public function exportSql(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-needed'));

        return $this->tableExporter->export(ExportFormat::Sql, 'site_redirect', $this->fetchExportRows());
    }

    #[AdminRoute]
    public function exportCsv(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-needed'));

        return $this->tableExporter->export(ExportFormat::Csv, 'site_redirect', $this->fetchExportRows());
    }

    #[AdminRoute]
    public function exportJson(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted($this->configService->get('site-role-needed'));

        return $this->tableExporter->export(ExportFormat::Json, 'site_redirect', $this->fetchExportRows());
    }

    private function fetchExportRows(): array
    {
        return $this->connection->fetchAllAssociative('SELECT * FROM `site_redirect` ORDER BY `id`');
    }
}
