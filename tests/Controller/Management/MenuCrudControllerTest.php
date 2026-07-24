<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Controller\Management\MenuCrudController;
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Repository\MenuRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MenuCrudControllerTest extends TestCase
{
    private function createMenuRepositoryReturning(array $usedLocations): MenuRepository
    {
        $query = $this->createStub(Query::class);
        $query->method('getSingleColumnResult')->willReturn($usedLocations);

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $menuRepository = $this->createStub(MenuRepository::class);
        $menuRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        return $menuRepository;
    }

    private function createAdminContextProvider(?AdminContext $context = null): AdminContextProvider
    {
        $requestStack = new RequestStack();
        if (null !== $context) {
            $request = new Request();
            $request->attributes->set('easyadmin_context', $context);
            $requestStack->push($request);
        }

        return new AdminContextProvider($requestStack);
    }

    private function createAdminContext(Menu $menu): AdminContext
    {
        $entityDto = new EntityDto(Menu::class, new ClassMetadata(Menu::class), null, $menu);

        return AdminContext::forTesting(crudContext: CrudContext::forTesting(entityDto: $entityDto));
    }

    private function createController(
        ?MenuRepository $menuRepository = null,
        ?AdminContextProvider $adminContextProvider = null,
        ?CsrfTokenManagerInterface $csrfTokenManager = null,
    ): MenuCrudController {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('site-role-editor');

        return new MenuCrudController(
            $configService,
            $menuRepository ?? $this->createMenuRepositoryReturning([]),
            $translator,
            $adminContextProvider ?? $this->createAdminContextProvider(),
            $csrfTokenManager ?? $this->createStub(CsrfTokenManagerInterface::class),
        );
    }

    private function findFieldByProperty(iterable $fields, string $property): mixed
    {
        foreach ($fields as $field) {
            if ($property === $field->getAsDto()->getProperty()) {
                return $field;
            }
        }

        return null;
    }

    // --- configureActions --------------------------------------------------------------------------------

    // Detail adds no information beyond what edit already shows - disabled entirely, and a Cancel action lets the admin back out of a create/edit without saving
    public function testConfigureActionsDisablesDetailAndAddsCancelOnNewAndEdit(): void
    {
        $controller = $this->createController();

        $actions = $controller->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        );

        $this->assertContains(Action::DETAIL, $actions->getAsDto(null)->getDisabledActions());
        $this->assertNotNull($actions->getAsDto(Crud::PAGE_NEW)->getAction(Crud::PAGE_NEW, 'cancel'));
        $this->assertNotNull($actions->getAsDto(Crud::PAGE_EDIT)->getAction(Crud::PAGE_EDIT, 'cancel'));
    }

    // --- configureFields: location ------------------------------------------------------------------------

    // Avoids ever hitting the DB-level unique constraint on Menu::$location - only locations not already used by another row can be picked when creating a new one
    public function testConfigureFieldsLocationExcludesAlreadyUsedLocationsOnNewPage(): void
    {
        $menuRepository = $this->createMenuRepositoryReturning([Menu::LOCATION_NAVBAR, Menu::LOCATION_FOOTER]);
        $controller = $this->createController(menuRepository: $menuRepository);

        $fields = $controller->configureFields(Crud::PAGE_NEW);
        $location = $this->findFieldByProperty($fields, 'location');
        $choices = $location->getAsDto()->getCustomOption(ChoiceField::OPTION_CHOICES);

        $this->assertSame([Menu::LOCATION_EMAIL_HEADER, Menu::LOCATION_EMAIL_FOOTER], array_keys($choices));
    }

    // Editing an existing row: its own already-used location must still be selectable (it's disabled anyway, see below), so every location is offered regardless of what's already taken
    public function testConfigureFieldsLocationOffersEveryLocationOnEditPage(): void
    {
        $menuRepository = $this->createMenuRepositoryReturning([Menu::LOCATION_NAVBAR]);
        $controller = $this->createController(menuRepository: $menuRepository);

        $fields = $controller->configureFields(Crud::PAGE_EDIT);
        $location = $this->findFieldByProperty($fields, 'location');
        $choices = $location->getAsDto()->getCustomOption(ChoiceField::OPTION_CHOICES);

        $this->assertSame(
            [Menu::LOCATION_NAVBAR, Menu::LOCATION_FOOTER, Menu::LOCATION_EMAIL_HEADER, Menu::LOCATION_EMAIL_FOOTER],
            array_keys($choices)
        );
    }

    public function testConfigureFieldsLocationIsEditableOnNewPage(): void
    {
        $fields = $this->createController()->configureFields(Crud::PAGE_NEW);
        $location = $this->findFieldByProperty($fields, 'location');

        $this->assertFalse($location->getAsDto()->getFormTypeOptions()['disabled'] ?? null);
    }

    // A row's location is fixed once created - only reassignable by deleting and recreating the row
    public function testConfigureFieldsLocationIsDisabledOnEditPage(): void
    {
        $fields = $this->createController()->configureFields(Crud::PAGE_EDIT);
        $location = $this->findFieldByProperty($fields, 'location');

        $this->assertTrue($location->getAsDto()->getFormTypeOptions()['disabled'] ?? null);
    }

    // --- configureFields: blocks row_attr ------------------------------------------------------------------

    // No entity yet (new menu) - blockMoveRowAttr() has nothing to key the move on, so the "blocks" field gets no row_attr at all rather than a partial/broken one
    public function testConfigureFieldsBlocksFieldHasNoRowAttrOnNewPage(): void
    {
        $fields = $this->createController()->configureFields(Crud::PAGE_NEW);
        $blocks = $this->findFieldByProperty($fields, 'blocks');

        $this->assertSame([], $blocks->getAsDto()->getFormTypeOptions()['row_attr'] ?? null);
    }

    // Editing an already-saved menu - the "blocks" field's row_attr carries what UiBundle's ea-sortable.js/BlockMoveController needs to relocate a Block into/out of a container (see BlockMoveRowAttrTrait)
    public function testConfigureFieldsBlocksFieldRowAttrCarriesBlockMoveDataOnEditPage(): void
    {
        $menu = (new Menu())->setLocation(Menu::LOCATION_NAVBAR);
        (new \ReflectionProperty(Menu::class, 'id'))->setValue($menu, 7);

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('getToken')->willReturn(new CsrfToken('management_ui_block_move', 'token123'));

        $controller = $this->createController(
            adminContextProvider: $this->createAdminContextProvider($this->createAdminContext($menu)),
            csrfTokenManager: $csrfTokenManager,
        );
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/admin/ui/block/move');
        $container = new Container();
        $container->set('router', $router);
        $controller->setContainer($container);

        $fields = $controller->configureFields(Crud::PAGE_EDIT);
        $rowAttr = $this->findFieldByProperty($fields, 'blocks')->getAsDto()->getFormTypeOptions()['row_attr'] ?? [];

        $this->assertSame('menu', $rowAttr['data-block-owner-type']);
        $this->assertSame(7, $rowAttr['data-block-owner-id']);
        $this->assertSame('/admin/ui/block/move', $rowAttr['data-block-move-url']);
        $this->assertSame('token123', $rowAttr['data-block-move-csrf-token']);
    }
}
