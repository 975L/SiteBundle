<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Controller\Management;

use App\Entity\User;
use c975L\ConfigBundle\Management\EasyAdminActionHelper;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\Export\ExportFormat;
use c975L\ConfigBundle\Service\Export\TableExporter;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\ActionGroup;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;

use function Symfony\Component\Translation\t;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly EntityManagerInterface $entityManager,
        private readonly TableExporter $tableExporter,
        private readonly Security $security,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    // Relies on EasyAdmin's auto-discovery of App\Entity\User's own fields (which vary per app), except for:
    // - the hashed password, excluded so it's never displayed or overwritten from the backoffice
    // - creation/modification, made readonly since they're set automatically
    // - isVerified, made readonly since it must only be set by EmailVerifier upon email confirmation
    // "roles" is excluded by EasyAdmin's own auto-discovery (JSON columns are never auto-discovered),
    // so it's added explicitly as a proper multiple-choice field
    public function configureFields(string $pageName): iterable
    {
        foreach (parent::configureFields($pageName) as $field) {
            $property = $field->getAsDto()->getProperty();

            if ('password' === $property) {
                continue;
            }

            if (in_array($property, ['creation', 'modification', 'isVerified'], true)) {
                yield $field->setFormTypeOption('disabled', 'disabled');

                continue;
            }

            yield $field;
        }

        // ROLE_USER is excluded, it's already granted by default to every user (see User::getRoles())
        // Not required, since having none selected simply means the user only has that default role
        yield ChoiceField::new('roles')
            ->setLabel(t('label.roles', [], 'site'))
            ->setChoices($this->roleChoices())
            ->allowMultipleChoices()
            ->renderExpanded(false)
            ->setRequired(false);
    }

    // Reads the extra roles selectable in the backoffice from the "user-roles-available" config (JSON array)
    // ROLE_SUPER_ADMIN is stripped from the choices (so from the submitted form's allowed values too,
    // Symfony's ChoiceType rejects anything outside them) unless the acting user already holds it —
    // otherwise a plain ROLE_ADMIN could grant it to themselves or anyone else and bypass ConfigBundle's
    // "restricted" configs entirely
    private function roleChoices(): array
    {
        $roles = (array) $this->configService->get('user-roles-available');

        if (!$this->security->isGranted('ROLE_SUPER_ADMIN')) {
            $roles = array_filter($roles, static fn (string $role): bool => 'ROLE_SUPER_ADMIN' !== $role);
        }

        return array_combine($roles, $roles);
    }

    public function configureActions(Actions $actions): Actions
    {
        $exportGroup = ActionGroup::new('export', t('label.export', [], 'site'), 'fa fa-download')
            ->createAsGlobalActionGroup()
            ->addAction(Action::new('exportSql', 'SQL')->linkToCrudAction('exportSql'))
            ->addAction(Action::new('exportCsv', 'CSV')->linkToCrudAction('exportCsv'))
            ->addAction(Action::new('exportJson', 'JSON')->linkToCrudAction('exportJson'))
        ;

        return $actions
            ->disable(Action::NEW)
            ->disable(Action::DETAIL)
            ->add(Crud::PAGE_INDEX, $exportGroup)
            ->update(Crud::PAGE_INDEX, Action::EDIT, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.edit', [], 'EasyAdminBundle'),
            ))
            ->update(Crud::PAGE_INDEX, Action::DELETE, fn (Action $action) => EasyAdminActionHelper::toIconOnly(
                $action,
                $this->translator->trans('action.delete', [], 'EasyAdminBundle'),
            ))
            ->setPermission(Action::INDEX, $this->configService->get('site-role-admin'))
            ->setPermission(Action::EDIT, $this->configService->get('site-role-admin'))
            ->setPermission(Action::DELETE, $this->configService->get('site-role-admin'))
            ->setPermission('exportSql', 'ROLE_SUPER_ADMIN')
            ->setPermission('exportCsv', 'ROLE_SUPER_ADMIN')
            ->setPermission('exportJson', 'ROLE_SUPER_ADMIN')
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->showEntityActionsInlined()
            ->setEntityPermission($this->configService->get('site-role-admin'))
            ->overrideTemplate('crud/index', '@c975LSite/management/user_crud_index.html.twig')
        ;
    }

    #[AdminRoute]
    public function exportSql(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        return $this->tableExporter->export(ExportFormat::Sql, $this->getUserTableName(), $this->fetchExportRows());
    }

    #[AdminRoute]
    public function exportCsv(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        return $this->tableExporter->export(ExportFormat::Csv, $this->getUserTableName(), $this->fetchExportRows());
    }

    #[AdminRoute]
    public function exportJson(AdminContext $context): Response
    {
        $this->denyAccessUnlessGranted('ROLE_SUPER_ADMIN');

        return $this->tableExporter->export(ExportFormat::Json, $this->getUserTableName(), $this->fetchExportRows());
    }

    // The hashed password is never exported, exposing it brings no legitimate use and only adds risk
    private function fetchExportRows(): array
    {
        $rows = $this->entityManager->getConnection()
            ->fetchAllAssociative("SELECT * FROM `{$this->getUserTableName()}`");

        return array_map(static fn (array $row): array => array_diff_key($row, ['password' => null]), $rows);
    }

    private function getUserTableName(): string
    {
        return $this->entityManager->getClassMetadata(User::class)->getTableName();
    }
}
