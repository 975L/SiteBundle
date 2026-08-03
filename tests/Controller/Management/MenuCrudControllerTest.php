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
use c975L\UiBundle\Registry\BlockRegistry;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Registry\AdminControllerRegistryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Router\AdminRouteGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class MenuCrudControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private function createMenuRepositoryReturning(array $usedLocations, ?Menu $existing = null): MenuRepository
    {
        $query = $this->createStub(Query::class);
        $query->method('getSingleColumnResult')->willReturn($usedLocations);

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $menuRepository = $this->createStub(MenuRepository::class);
        $menuRepository->method('createQueryBuilder')->willReturn($queryBuilder);
        $menuRepository->method('findOneByLocation')->willReturn($existing);

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

    // AdminUrlGenerator is final - can't be mocked, so it's built for real with stubbed interface collaborators, matching how the framework itself wires it (same as PageCrudControllerTest)
    private function createAdminUrlGenerator(string $generatedUrl = '/management/menus'): AdminUrlGenerator
    {
        $adminControllers = $this->createStub(AdminControllerRegistryInterface::class);
        $adminControllers->method('getDashboardCount')->willReturn(1);
        $adminControllers->method('getFirstDashboard')->willReturn('App\\Controller\\Management\\DashboardController');
        $adminControllers->method('getFirstDashboardRoute')->willReturn('admin');

        $routeGenerator = $this->createStub(AdminRouteGeneratorInterface::class);
        $routeGenerator->method('findRouteName')->willReturn('admin');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn($generatedUrl);

        return new AdminUrlGenerator(
            $this->createStub(AdminContextProviderInterface::class),
            $urlGenerator,
            $adminControllers,
            $routeGenerator,
            new ArrayAdapter(),
        );
    }

    private function createController(
        ?MenuRepository $menuRepository = null,
        ?AdminContextProvider $adminContextProvider = null,
        ?BlockMoveRowAttrBuilder $blockMoveRowAttrBuilder = null,
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
            $blockMoveRowAttrBuilder ?? $this->createBlockMoveRowAttrBuilder(),
            $this->createAdminUrlGenerator(),
        );
    }

    // A POST carrying the location one of the index's own "create" buttons stands for
    private function createRequest(string $location, string $token = 'valid'): Request
    {
        return new Request(request: ['location' => $location, '_token' => $token]);
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

    // Detail adds no information beyond what edit already shows, and New is replaced by the index's own per-location buttons (see create()) - a Cancel action lets the admin back out of an edit without saving
    // A real builder over stubs: what these tests assert is the row_attr a field ends up carrying, not the builder's own wiring (covered by UiBundle)
    private function createBlockMoveRowAttrBuilder(string $url = '/admin/ui/block/move', string $token = 'token123'): BlockMoveRowAttrBuilder
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn($url);

        $csrfTokenManager = $this->createStub(CsrfTokenManagerInterface::class);
        $csrfTokenManager->method('getToken')->willReturn(new CsrfToken(BlockMoveRowAttrBuilder::ROUTE, $token));

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new BlockMoveRowAttrBuilder($urlGenerator, $csrfTokenManager, $translator);
    }

    public function testConfigureActionsDisablesDetailAndNewAndAddsCancelOnEdit(): void
    {
        $controller = $this->createController();

        $actions = $controller->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        );

        $disabled = $actions->getAsDto(null)->getDisabledActions();

        $this->assertContains(Action::DETAIL, $disabled);
        $this->assertContains(Action::NEW, $disabled);
        $this->assertNotNull($actions->getAsDto(Crud::PAGE_EDIT)->getAction(Crud::PAGE_EDIT, 'cancel'));
    }

    // --- configureFields: location ------------------------------------------------------------------------

    // Every location is offered, whatever is already taken: the field is only ever rendered on the edit page, disabled, its value having been set once and for all by create()
    public function testConfigureFieldsLocationOffersEveryLocation(): void
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

    // A row's location is set once by create() and fixed for good - only reassignable by deleting and recreating the row
    public function testConfigureFieldsLocationIsDisabledOnEditPage(): void
    {
        $fields = $this->createController()->configureFields(Crud::PAGE_EDIT);
        $location = $this->findFieldByProperty($fields, 'location');

        $this->assertTrue($location->getAsDto()->getFormTypeOptions()['disabled'] ?? null);
    }

    // --- configureFields: blocks --------------------------------------------------------------------------

    // A menu is created empty by create(), then composed on the edit page - the block picker's choices depending on the location that action has just set
    public function testConfigureFieldsBlocksFieldIsOnlyDisplayedWhenUpdating(): void
    {
        $blocks = $this->findFieldByProperty($this->createController()->configureFields(Crud::PAGE_EDIT), 'blocks');

        $this->assertFalse($blocks->getAsDto()->isDisplayedOn(Crud::PAGE_NEW));
        $this->assertTrue($blocks->getAsDto()->isDisplayedOn(Crud::PAGE_EDIT));
    }

    // A navigation bar is a plain list of links: its picker only ever offers "menu_link" (the exclusive BlockRegistry::MENU_NAVBAR_CONTEXT), any other kind belonging in the page itself
    public function testConfigureFieldsBlocksUseTheNavbarRestrictedContextForTheNavbar(): void
    {
        $controller = $this->createController(
            adminContextProvider: $this->createAdminContextProvider($this->createAdminContext((new Menu())->setLocation(Menu::LOCATION_NAVBAR)))
        );

        $blocks = $this->findFieldByProperty($controller->configureFields(Crud::PAGE_EDIT), 'blocks');

        $this->assertSame(BlockRegistry::MENU_NAVBAR_CONTEXT, $blocks->getAsDto()->getFormTypeOptions()['entry_options']['context'] ?? null);
    }

    // Every other location keeps the full picker - a footer legitimately holds text, social links, a logo...
    public function testConfigureFieldsBlocksUseTheFullMenuContextForOtherLocations(): void
    {
        $controller = $this->createController(
            adminContextProvider: $this->createAdminContextProvider($this->createAdminContext((new Menu())->setLocation(Menu::LOCATION_FOOTER)))
        );

        $blocks = $this->findFieldByProperty($controller->configureFields(Crud::PAGE_EDIT), 'blocks');

        $this->assertSame(BlockRegistry::MENU_CONTEXT, $blocks->getAsDto()->getFormTypeOptions()['entry_options']['context'] ?? null);
    }

    // No entity yet (new menu) - blockMoveRowAttr() has nothing to key the move on, so the "blocks" field gets no row_attr at all rather than a partial/broken one
    public function testConfigureFieldsBlocksFieldHasNoRowAttrWithoutASavedEntity(): void
    {
        $fields = $this->createController()->configureFields(Crud::PAGE_EDIT);
        $blocks = $this->findFieldByProperty($fields, 'blocks');

        $this->assertSame([], $blocks->getAsDto()->getFormTypeOptions()['row_attr'] ?? null);
    }

    // Editing an already-saved menu - the "blocks" field's row_attr carries what UiBundle's ea-sortable.js/BlockMoveController needs to relocate a Block into/out of a container (see UiBundle's BlockMoveRowAttrBuilder)
    public function testConfigureFieldsBlocksFieldRowAttrCarriesBlockMoveDataOnEditPage(): void
    {
        $menu = (new Menu())->setLocation(Menu::LOCATION_NAVBAR);
        (new \ReflectionProperty(Menu::class, 'id'))->setValue($menu, 7);

        $controller = $this->createController(
            adminContextProvider: $this->createAdminContextProvider($this->createAdminContext($menu)),
            blockMoveRowAttrBuilder: $this->createBlockMoveRowAttrBuilder(),
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

    // --- create --------------------------------------------------------------------------------------------

    public function testCreateDeniesAccessBelowEditor(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(false),
        ]));

        $controller->create($this->createRequest(Menu::LOCATION_NAVBAR), $this->createStub(EntityManagerInterface::class));
    }

    // The index's buttons POST a token - a request forged without one must not create anything
    public function testCreateRejectsAnInvalidCsrfToken(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(false),
        ]));

        $controller->create($this->createRequest(Menu::LOCATION_NAVBAR), $this->createStub(EntityManagerInterface::class));
    }

    // Only one of the four known locations is ever creatable, whatever a hand-crafted POST carries
    public function testCreateRefusesAnUnknownLocation(): void
    {
        $this->expectException(NotFoundHttpException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
        ]));

        $controller->create($this->createRequest('sidebar'), $this->createStub(EntityManagerInterface::class));
    }

    // The whole point of the action: one click creates the row and lands on its edit page, no form in between
    public function testCreatePersistsTheMenuAndRedirectsToItsEditPage(): void
    {
        $menuRepository = $this->createMenuRepositoryReturning([]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('persist')->with($this->callback(
            static fn (Menu $menu): bool => Menu::LOCATION_NAVBAR === $menu->getLocation()
        ));
        $entityManager->expects($this->once())->method('flush');

        $controller = $this->createController(menuRepository: $menuRepository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
        ]));

        $response = $controller->create($this->createRequest(Menu::LOCATION_NAVBAR), $entityManager);

        $this->assertSame(302, $response->getStatusCode());
    }

    // Double submit, or a stale index left open in another tab: the location already has its row, which is opened as-is instead of hitting the DB-level unique constraint on Menu::$location
    public function testCreateOpensTheExistingMenuWhenTheLocationIsAlreadyTaken(): void
    {
        $existing = (new Menu())->setLocation(Menu::LOCATION_NAVBAR);
        (new \ReflectionProperty(Menu::class, 'id'))->setValue($existing, 7);

        $menuRepository = $this->createMenuRepositoryReturning([Menu::LOCATION_NAVBAR], $existing);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $controller = $this->createController(menuRepository: $menuRepository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
        ]));

        $response = $controller->create($this->createRequest(Menu::LOCATION_NAVBAR), $entityManager);

        $this->assertSame(302, $response->getStatusCode());
    }
}
