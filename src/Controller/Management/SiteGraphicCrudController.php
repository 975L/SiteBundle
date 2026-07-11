<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Controller\Management;

use c975L\ConfigBundle\Management\AlertBuilder;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Management\SiteGraphicAlertProvider;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\MediaRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use Symfony\Component\Validator\Constraints\File as FileConstraint;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;

use function Symfony\Component\Translation\t;

// Manages the site-wide graphics (favicon, apple-touch-icon, default og-image, logo), stored as
// c975L\UiBundle\Entity\Media rows carrying a "role" instead of being attached to a Block. For singleton
// roles the file is always saved at a fixed name at the root of public/ (see UiMediaNamer), so it stays
// reachable at its well-known URL (e.g. /favicon.ico) whatever gets re-uploaded. The error-image role is
// repeatable: several rows can share it, forming the pool the error pages pick a random image from.
class SiteGraphicCrudController extends AbstractCrudController
{
    private const ROLE_LABELS = [
        Media::ROLE_FAVICON => 'label.favicon',
        Media::ROLE_APPLE_TOUCH_ICON => 'label.apple_touch_icon',
        Media::ROLE_OG_IMAGE => 'label.og_image',
        Media::ROLE_LOGO => 'label.logo',
        Media::ROLE_ERROR_IMAGE => 'label.error_image',
    ];

    // Roles allowed to have several rows (e.g. a pool of images picked at random), unlike the singleton graphics
    private const REPEATABLE_ROLES = [
        Media::ROLE_ERROR_IMAGE,
    ];

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly MediaRepository $mediaRepository,
        private readonly SiteGraphicAlertProvider $siteGraphicAlertProvider,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Media::class;
    }

    public function createIndexQueryBuilder(...$args): QueryBuilder
    {
        return parent::createIndexQueryBuilder(...$args)
            ->andWhere('entity.role IS NOT NULL')
        ;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('label.site_graphic', [], 'site'))
            ->setEntityLabelInPlural(t('label.site_graphics', [], 'site'))
            ->setEntityPermission($this->configService->get('site-role-editor'))
            ->overrideTemplate('crud/index', '@c975LSite/management/site_graphic_crud_index.html.twig')
        ;
    }

    public function configureResponseParameters(KeyValueStore $responseParameters): KeyValueStore
    {
        if (Crud::PAGE_INDEX === $responseParameters->get('pageName')) {
            $responseParameters->set('alerts', AlertBuilder::groupBySeverity($this->siteGraphicAlertProvider->getAlerts()));
            $responseParameters->set('alertsTitle', $this->translator->trans(
                'label.items_not_filled_for',
                ['%entity%' => $this->translator->trans('label.site_graphics', [], 'site')],
                'config'
            ));
        }

        return $responseParameters;
    }

    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-editor');

        return $actions
            ->setPermission(Action::INDEX, $role)
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            ->setPermission(Action::DETAIL, $role)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        $isNew = Crud::PAGE_NEW === $pageName;

        // Only singleton roles not yet used can be picked when creating a new row - repeatable roles
        // (e.g. error-image) stay selectable even after already being used elsewhere
        $usedSingletonRoles = array_diff(
            $this->mediaRepository->createQueryBuilder('m')
                ->select('m.role')
                ->where('m.role IS NOT NULL')
                ->getQuery()
                ->getSingleColumnResult(),
            self::REPEATABLE_ROLES
        );
        $selectableRoles = $isNew ? array_diff_key(self::ROLE_LABELS, array_flip($usedSingletonRoles)) : self::ROLE_LABELS;

        $roleChoices = [];
        foreach ($selectableRoles as $roleSlug => $labelKey) {
            $roleChoices[$roleSlug] = t($labelKey, [], 'site');
        }

        return [
            IdField::new('id')->onlyOnIndex(),

            ChoiceField::new('role')
                ->setLabel(t('label.role', [], 'site'))
                ->setTranslatableChoices($roleChoices)
                ->setFormTypeOption('disabled', !$isNew)
                ->setRequired(true),

            Field::new('file')
                ->setLabel(t('label.file', [], 'site'))
                ->setHelp(t('label.site_graphic_file_help', [], 'site'))
                ->setFormType(VichImageType::class)
                ->setFormTypeOptions([
                    'required' => $isNew,
                    'allow_delete' => true,
                    'download_uri' => true,
                    'asset_helper' => true,
                    'constraints' => [
                        new FileConstraint(maxSize: '2M'),
                    ],
                ])
                ->onlyOnForms(),

            TextField::new('filename')
                ->setLabel(t('label.file', [], 'site'))
                ->onlyOnIndex(),
        ];
    }
}
