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
use c975L\SiteBundle\Controller\Management\CollectionItemCrudController;
use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Registry\AdminControllerRegistryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Router\AdminRouteGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CollectionItemCrudControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    // AdminUrlGenerator is final - can't be mocked, so it's built for real with stubbed interface collaborators, same pattern as PageCrudControllerTest
    private function createAdminUrlGenerator(): AdminUrlGenerator
    {
        $adminControllers = $this->createStub(AdminControllerRegistryInterface::class);
        $adminControllers->method('getDashboardCount')->willReturn(1);
        $adminControllers->method('getFirstDashboard')->willReturn('App\\Controller\\Management\\DashboardController');
        $adminControllers->method('getFirstDashboardRoute')->willReturn('admin');

        $routeGenerator = $this->createStub(AdminRouteGeneratorInterface::class);
        $routeGenerator->method('findRouteName')->willReturn('admin');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/management/collection-items');

        return new AdminUrlGenerator(
            $this->createStub(AdminContextProviderInterface::class),
            $urlGenerator,
            $adminControllers,
            $routeGenerator,
            new ArrayAdapter(),
        );
    }

    // Simulates browsing the index already scoped to a group (?group=...), or the un-scoped "pick a group" screen when $group is null
    private function createRequestStackWithGroup(?string $group): RequestStack
    {
        $request = new Request(null !== $group ? ['group' => $group] : []);

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return $requestStack;
    }

    private function createController(
        ?CollectionItemRepository $collectionItemRepository = null,
        ?SluggerInterface $slugger = null,
        ?AdminUrlGenerator $adminUrlGenerator = null,
        ?RequestStack $requestStack = null,
    ): CollectionItemCrudController {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_EDITOR');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new CollectionItemCrudController(
            $configService,
            $translator,
            $slugger ?? new AsciiSlugger(),
            $collectionItemRepository ?? $this->createStub(CollectionItemRepository::class),
            $adminUrlGenerator ?? $this->createAdminUrlGenerator(),
            $requestStack ?? new RequestStack(),
        );
    }

    private function invokePrivate(CollectionItemCrudController $controller, string $method, array $args = []): mixed
    {
        return (new \ReflectionMethod($controller, $method))->invoke($controller, ...$args);
    }

    private function withId(CollectionItem $item, int $id): CollectionItem
    {
        (new \ReflectionProperty(CollectionItem::class, 'id'))->setValue($item, $id);

        return $item;
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

    // --- currentGroup (private) -----------------------------------------------------------------------

    public function testCurrentGroupReturnsNullWhenThereIsNoRequest(): void
    {
        $controller = $this->createController(requestStack: new RequestStack());

        $this->assertNull($this->invokePrivate($controller, 'currentGroup'));
    }

    public function testCurrentGroupReturnsNullWhenNoGroupQueryParam(): void
    {
        $controller = $this->createController(requestStack: $this->createRequestStackWithGroup(null));

        $this->assertNull($this->invokePrivate($controller, 'currentGroup'));
    }

    public function testCurrentGroupReturnsTheGroupQueryParam(): void
    {
        $controller = $this->createController(requestStack: $this->createRequestStackWithGroup('projects'));

        $this->assertSame('projects', $this->invokePrivate($controller, 'currentGroup'));
    }

    // --- configureFields --------------------------------------------------------------------------------

    // No group selected (e.g. reaching "new" directly): the free-text group field stays empty, same as before this screen existed
    public function testConfigureFieldsDoesNotPrefillGroupOnNewPageWithoutACurrentGroup(): void
    {
        $controller = $this->createController(requestStack: $this->createRequestStackWithGroup(null));
        $fields = $controller->configureFields(Crud::PAGE_NEW);
        $group = $this->findFieldByProperty($fields, 'group');

        $this->assertArrayNotHasKey('data', $group->getAsDto()->getFormTypeOptions());
    }

    // Creating a new item from within an already-filtered group view prefills it, so an editor doesn't have to retype it by hand (a typo would silently create a brand-new group)
    public function testConfigureFieldsPrefillsGroupOnNewPageWhenACurrentGroupIsSet(): void
    {
        $controller = $this->createController(requestStack: $this->createRequestStackWithGroup('projects'));
        $fields = $controller->configureFields(Crud::PAGE_NEW);
        $group = $this->findFieldByProperty($fields, 'group');

        $this->assertSame('projects', $group->getAsDto()->getFormTypeOptions()['data'] ?? null);
    }

    // Editing an existing item must never override its own already-persisted group with the currently browsed one
    public function testConfigureFieldsDoesNotPrefillGroupOnEditPageEvenWithACurrentGroup(): void
    {
        $controller = $this->createController(requestStack: $this->createRequestStackWithGroup('projects'));
        $fields = $controller->configureFields(Crud::PAGE_EDIT);
        $group = $this->findFieldByProperty($fields, 'group');

        $this->assertArrayNotHasKey('data', $group->getAsDto()->getFormTypeOptions());
    }

    // --- configureActions --------------------------------------------------------------------------------

    public function testConfigureActionsBuildsWithoutError(): void
    {
        $actions = $this->createController()->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        );

        $this->assertInstanceOf(Actions::class, $actions);
    }

    // --- persistEntity / updateEntity ----------------------------------------------------------------

    public function testPersistEntitySlugifiesAndDelegatesToParent(): void
    {
        $controller = $this->createController();
        $item = (new CollectionItem())->setGroup('projects')->setSlug('Néw Ïtem!');

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->once())->method('persist')->with($item);
        $manager->expects($this->once())->method('flush');

        $controller->persistEntity($manager, $item);

        $this->assertSame('new-item', $item->getSlug());
    }

    public function testUpdateEntitySlugifiesAndDelegatesToParent(): void
    {
        $controller = $this->createController();
        $item = (new CollectionItem())->setGroup('projects')->setSlug('Héllo Wörld!');

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->once())->method('persist')->with($item);
        $manager->expects($this->once())->method('flush');

        $controller->updateEntity($manager, $item);

        $this->assertSame('hello-world', $item->getSlug());
    }

    // --- slugifyItem (private) ----------------------------------------------------------------------

    public function testSlugifyItemNormalizesAccentsSpacesAndCase(): void
    {
        $controller = $this->createController();
        $item = (new CollectionItem())->setGroup('projects')->setSlug('Héllo Wörld!');

        $this->invokePrivate($controller, 'slugifyItem', [$item]);

        $this->assertSame('hello-world', $item->getSlug());
    }

    public function testSlugifyItemDoesNothingWhenSlugIsNull(): void
    {
        $controller = $this->createController();
        $item = new CollectionItem();

        $this->invokePrivate($controller, 'slugifyItem', [$item]);

        $this->assertNull($item->getSlug());
    }

    // Collision check is scoped to the item's own "group" - a same-named slug in another group never collides
    public function testSlugifyItemAppendsASuffixOnCollisionWithinTheSameGroup(): void
    {
        $repository = $this->createStub(CollectionItemRepository::class);
        $repository->method('findOneByGroupAndSlug')->willReturnMap([
            ['projects', 'my-item', $this->withId(new CollectionItem(), 1)],
            ['projects', 'my-item-2', null],
        ]);

        $controller = $this->createController($repository);
        $item = (new CollectionItem())->setGroup('projects')->setSlug('My Item');

        $this->invokePrivate($controller, 'slugifyItem', [$item]);

        $this->assertSame('my-item-2', $item->getSlug());
    }

    // Re-saving an item with its own unchanged slug must not collide with itself
    public function testSlugifyItemDoesNotCollideWithItself(): void
    {
        $repository = $this->createStub(CollectionItemRepository::class);
        $repository->method('findOneByGroupAndSlug')->willReturn($this->withId(
            (new CollectionItem())->setGroup('projects')->setSlug('my-item'),
            5
        ));

        $controller = $this->createController($repository);
        $item = $this->withId((new CollectionItem())->setGroup('projects')->setSlug('My Item'), 5);

        $this->invokePrivate($controller, 'slugifyItem', [$item]);

        $this->assertSame('my-item', $item->getSlug());
    }

    // --- reorder --------------------------------------------------------------------------------------

    private function createReorderRequest(array $payload): Request
    {
        return new Request([], [], [], [], [], [], json_encode($payload));
    }

    public function testReorderPersistsPositionsInTheSubmittedOrder(): void
    {
        $item1 = $this->withId((new CollectionItem())->setGroup('sites'), 1);
        $item2 = $this->withId((new CollectionItem())->setGroup('sites'), 2);
        $item3 = $this->withId((new CollectionItem())->setGroup('sites'), 3);

        $repository = $this->createStub(CollectionItemRepository::class);
        $repository->method('findBy')->willReturn([$item1, $item2, $item3]);

        $controller = $this->createController($repository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
        ]));

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->once())->method('flush');

        $response = $controller->reorder(
            $this->createReorderRequest(['group' => 'sites', 'ids' => [3, 1, 2], '_token' => 'valid-token']),
            $entityManager,
        );

        $this->assertSame(0, $item3->getPosition());
        $this->assertSame(1, $item1->getPosition());
        $this->assertSame(2, $item2->getPosition());
        $this->assertSame(['success' => true], json_decode($response->getContent(), true));
    }

    public function testReorderDeniesAccessWhenNotGranted(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(false),
        ]));

        $controller->reorder($this->createReorderRequest([]), $this->createStub(EntityManagerInterface::class));
    }

    public function testReorderDeniesAccessWhenCsrfTokenIsInvalid(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(false),
        ]));

        $controller->reorder(
            $this->createReorderRequest(['group' => 'sites', 'ids' => [1], '_token' => 'invalid-token']),
            $this->createStub(EntityManagerInterface::class),
        );
    }

    // An id whose item doesn't belong to the submitted group never reaches setPosition() - a tampered payload could otherwise reorder another group's items
    public function testReorderDeniesAccessWhenAnItemDoesNotBelongToTheSubmittedGroup(): void
    {
        $this->expectException(AccessDeniedException::class);

        $item = $this->withId((new CollectionItem())->setGroup('other-group'), 1);

        $repository = $this->createStub(CollectionItemRepository::class);
        $repository->method('findBy')->willReturn([$item]);

        $controller = $this->createController($repository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
        ]));

        $controller->reorder(
            $this->createReorderRequest(['group' => 'sites', 'ids' => [1], '_token' => 'valid-token']),
            $this->createStub(EntityManagerInterface::class),
        );
    }
}
