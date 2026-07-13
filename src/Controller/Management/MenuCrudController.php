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
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Repository\MenuRepository;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Form\BlockType;
use c975L\UiBundle\Form\Util\CollectionReconciler;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\CollectionField;
use EasyCorp\Bundle\EasyAdminBundle\Field\IdField;
use Symfony\Component\Form\FormBuilderInterface;
use Symfony\Component\Form\FormEvent;
use Symfony\Component\Form\FormEvents;

use function Symfony\Component\Translation\t;

// Manages the site-wide menus (navbar, footer, email-footer), each owning a single ordered collection
// of Block rows - see Menu::LOCATION_*. Menu links are the "menu_link" Block kind (MenuLinkType),
// sortable alongside any other block
class MenuCrudController extends AbstractCrudController
{
    private const LOCATION_LABELS = [
        Menu::LOCATION_NAVBAR => 'label.navbar',
        Menu::LOCATION_FOOTER => 'label.footer',
        Menu::LOCATION_EMAIL_FOOTER => 'label.email_footer',
    ];

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly MenuRepository $menuRepository,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Menu::class;
    }

    // Removing the very last block also leaves nothing submitted at all for "blocks" (an HTML form
    // can't represent an empty array, only an absent key), which has to be normalized to [] below or
    // Symfony skips add/remove handling entirely for the field (see PageCrudController for the same trick)
    public function createEditFormBuilder(EntityDto $entityDto, KeyValueStore $formOptions, AdminContext $context): FormBuilderInterface
    {
        $formBuilder = parent::createEditFormBuilder($entityDto, $formOptions, $context);

        $formBuilder->addEventListener(FormEvents::PRE_SUBMIT, function (FormEvent $event): void {
            $data = $event->getData();
            if (!is_array($data)) {
                return;
            }

            $menu = $event->getForm()->getData();
            if ($menu instanceof Menu) {
                CollectionReconciler::pruneRemoved(
                    $menu->getBlocks(),
                    $data['blocks'] ?? [],
                    static fn (Block $block) => $menu->removeBlock($block)
                );
                if (!isset($data['blocks'])) {
                    $data['blocks'] = [];
                }
            }

            $event->setData($data);
        });

        return $formBuilder;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular(t('label.menu', [], 'site'))
            ->setEntityLabelInPlural(t('label.menus', [], 'site'))
            ->setEntityPermission($this->configService->get('site-role-editor'))
        ;
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

        // Only locations not yet used can be picked when creating a new row - avoids ever hitting the
        // DB-level unique constraint on Menu::$location
        $usedLocations = $this->menuRepository->createQueryBuilder('m')
            ->select('m.location')
            ->getQuery()
            ->getSingleColumnResult();
        $selectableLocations = $isNew ? array_diff_key(self::LOCATION_LABELS, array_flip($usedLocations)) : self::LOCATION_LABELS;

        $locationChoices = [];
        foreach ($selectableLocations as $locationSlug => $labelKey) {
            $locationChoices[$locationSlug] = t($labelKey, [], 'site');
        }

        return [
            IdField::new('id')->onlyOnIndex(),

            ChoiceField::new('location')
                ->setLabel(t('label.location', [], 'site'))
                ->setTranslatableChoices($locationChoices)
                ->setFormTypeOption('disabled', !$isNew)
                ->setRequired(true),

            CollectionField::new('blocks')
                ->setLabel(t('label.blocks', [], 'ui'))
                ->setEntryType(BlockType::class)
                ->allowAdd()
                ->allowDelete()
                ->setFormTypeOption('by_reference', false)
                ->hideOnIndex(),
        ];
    }
}
