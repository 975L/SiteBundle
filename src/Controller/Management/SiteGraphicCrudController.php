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
use c975L\SiteBundle\Form\VichImageOptions;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\MediaRepository;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Contracts\Translation\TranslatorInterface;
use Vich\UploaderBundle\Form\Type\VichImageType;

use function Symfony\Component\Translation\t;

// Manages the site-wide graphics (favicon, apple-touch-icon, default og-image, logo), stored as c975L\UiBundle\Entity\Media rows carrying a "role" instead of being attached to a Block. For singleton roles the file is always saved at a fixed name at the root of public/ (see UiMediaNamer), so it stays reachable at its well-known URL (e.g. /favicon.ico) whatever gets re-uploaded. The error-image role is repeatable: several rows can share it, forming the pool the error pages pick a random image from.
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

    // Query parameter carrying the role picked on the index (or in a dashboard alert), which pre-fills the "new" form - see missingRoles()
    public const ROLE_PARAMETER = 'graphicRole';

    private ?array $creatableRoles = null;

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly MediaRepository $mediaRepository,
        private readonly TranslatorInterface $translator,
        private readonly AdminContextProvider $adminContextProvider,
        private readonly AdminUrlGenerator $adminUrlGenerator,
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
            ->overrideTemplate('crud/edit', '@c975LSite/management/site_graphic_crud_edit.html.twig')
            ->overrideTemplate('crud/new', '@c975LSite/management/site_graphic_crud_new.html.twig')
        ;
    }

    public function configureActions(Actions $actions): Actions
    {
        $role = $this->configService->get('site-role-editor');

        // Lets the admin back out of a create/edit without saving - mirrors EasyAdmin's own built-in actions (linkToCrudAction targeting INDEX, same as Action::INDEX itself)
        $cancelAction = Action::new('cancel', $this->translator->trans('action.cancel', [], 'EasyAdminBundle'), 'fa fa-times')
            ->linkToCrudAction(Action::INDEX)
            ->addCssClass('btn btn-secondary');

        return $actions
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
            ->setPermission(Action::NEW, $role)
            ->setPermission(Action::EDIT, $role)
            ->setPermission(Action::DELETE, $role)
            // Detail adds no information beyond what edit already shows
            ->disable(Action::DETAIL)
        ;
    }

    public function configureFields(string $pageName): iterable
    {
        $isNew = Crud::PAGE_NEW === $pageName;

        // Only roles still creatable can be picked when creating a new row, and the one a button/alert already picked leaves nothing to choose at all
        $selectableRoles = $isNew ? $this->creatableRoles() : self::ROLE_LABELS;
        $requestedRole = $isNew ? $this->requestedRole() : null;

        $roleChoices = [];
        foreach ($selectableRoles as $roleSlug => $labelKey) {
            $roleChoices[$roleSlug] = t($labelKey, [], 'site');
        }

        return [
            IdField::new('id')->onlyOnIndex(),

            ChoiceField::new('role')
                ->setLabel(t('label.role', [], 'site'))
                ->setTranslatableChoices($roleChoices)
                // A disabled field submits nothing and keeps the value carried by the entity, set by createEntity() for a requested role
                ->setFormTypeOption('disabled', !$isNew || null !== $requestedRole)
                ->setRequired(true),

            Field::new('file')
                ->setLabel(t('label.file', [], 'site'))
                ->setHelp(t('label.site_graphic_file_help', [], 'site'))
                ->setFormType(VichImageType::class)
                ->setFormTypeOptions(VichImageOptions::default('2M', $isNew))
                ->onlyOnForms(),

            TextField::new('filename')
                ->setLabel(t('label.file', [], 'site'))
                ->onlyOnIndex(),
        ];
    }

    // Hands the index template the graphics still missing, each rendered as its own button opening the upload form with the role already picked (see site_graphic_crud_index.html.twig)
    public function index(AdminContext $context): KeyValueStore | Response
    {
        $responseParameters = parent::index($context);

        if ($responseParameters instanceof KeyValueStore) {
            $responseParameters->set('missing_roles', $this->missingRoles());
        }

        return $responseParameters;
    }

    // Pre-fills the role asked for by the clicked button, the ChoiceField then being disabled (see configureFields)
    public function createEntity(string $entityFqcn): object
    {
        $media = new Media();

        $requestedRole = $this->requestedRole();
        if (null !== $requestedRole) {
            $media->setRole($requestedRole);
        }

        return $media;
    }

    // The singleton graphics not uploaded yet, as a list of "label + url to the pre-filled upload form" - empty once all four exist. Repeatable roles are left out: never missing, they are added through the plain "new" action
    private function missingRoles(): array
    {
        $missing = [];
        foreach (array_diff_key($this->creatableRoles(), array_flip(self::REPEATABLE_ROLES)) as $roleSlug => $labelKey) {
            $missing[] = [
                'label' => $this->translator->trans($labelKey, [], 'site'),
                'url' => $this->roleUrl($roleSlug),
            ];
        }

        return $missing;
    }

    // The roles a new row can still be created for, as "slug => label key" - singleton roles already uploaded are out, repeatable ones (e.g. error-image) always stay. Kept in memory, the same page asking for it several times (fields, requested role, buttons)
    private function creatableRoles(): array
    {
        if (null === $this->creatableRoles) {
            $usedSingletonRoles = array_diff(
                $this->mediaRepository->createQueryBuilder('m')
                    ->select('m.role')
                    ->where('m.role IS NOT NULL')
                    ->getQuery()
                    ->getSingleColumnResult(),
                self::REPEATABLE_ROLES
            );

            $this->creatableRoles = array_diff_key(self::ROLE_LABELS, array_flip($usedSingletonRoles));
        }

        return $this->creatableRoles;
    }

    // The role carried by the current request, ignored unless it is still creatable - a stale link (role uploaded in the meantime) then falls back to the plain choice list
    private function requestedRole(): ?string
    {
        $role = $this->adminContextProvider->getContext()?->getRequest()->query->getString(self::ROLE_PARAMETER);

        return null !== $role && isset($this->creatableRoles()[$role]) ? $role : null;
    }

    // The url of the upload form for the given role
    private function roleUrl(string $roleSlug): string
    {
        return $this->adminUrlGenerator
            ->unsetAll()
            ->setController(self::class)
            ->setAction(Action::NEW)
            ->set(self::ROLE_PARAMETER, $roleSlug)
            ->generateUrl();
    }
}
