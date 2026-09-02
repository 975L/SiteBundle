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
use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Repository\CollectionGroupRepository;
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
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CollectionItemCrudControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    // AdminUrlGenerator is final - can't be mocked, so it's built for real with stubbed interface collaborators, same pattern as PageCrudControllerTest
    // Everything on this screen is scoped by a "?collectionGroup=", and EasyAdmin's own url generator carries none of
    // it: without this, "Nouveau" opened a new() with no collection to attach to, which bounces back to the list of
    // collections - what an admin got instead of the form they asked for
    public function testTheNewActionCarriesTheCollectionTheScreenIsScopedBy(): void
    {
        $parameters = [];
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static function (string $route, array $routeParameters = []) use (&$parameters): string {
                $parameters = $routeParameters;

                return '/management/collection-items';
            }
        );

        $request = new Request(['collectionGroup' => '7']);
        $requestStack = new RequestStack([$request]);

        $group = new CollectionGroup();
        new \ReflectionProperty(CollectionGroup::class, 'id')->setValue($group, 7);

        $collectionGroupRepository = $this->createStub(CollectionGroupRepository::class);
        $collectionGroupRepository->method('find')->willReturn($group);

        $actions = $this->createController(
            null,
            $collectionGroupRepository,
            null,
            $this->createAdminUrlGenerator($urlGenerator),
            $requestStack,
        )->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::NEW)
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        );

        $new = $actions->getAsDto(Crud::PAGE_INDEX)->getAction(Crud::PAGE_INDEX, Action::NEW);
        $url = $new?->getUrl();

        $this->assertIsCallable($url);
        $url();
        $this->assertSame('7', (string) ($parameters['collectionGroup'] ?? ''));
    }

    private function createAdminUrlGenerator(?UrlGeneratorInterface $urlGenerator = null): AdminUrlGenerator
    {
        $adminControllers = $this->createStub(AdminControllerRegistryInterface::class);
        $adminControllers->method('getDashboardCount')->willReturn(1);
        $adminControllers->method('getFirstDashboard')->willReturn('App\\Controller\\Management\\DashboardController');
        $adminControllers->method('getFirstDashboardRoute')->willReturn('admin');

        $routeGenerator = $this->createStub(AdminRouteGeneratorInterface::class);
        $routeGenerator->method('findRouteName')->willReturn('admin');

        if (null === $urlGenerator) {
            $stub = $this->createStub(UrlGeneratorInterface::class);
            $stub->method('generate')->willReturn('/management/collection-items');
            $urlGenerator = $stub;
        }

        return new AdminUrlGenerator(
            $this->createStub(AdminContextProviderInterface::class),
            $urlGenerator,
            $adminControllers,
            $routeGenerator,
            new ArrayAdapter(),
        );
    }

    // Simulates browsing the index already scoped to a collection (?collectionGroup=<id>), or reaching it with none when $collectionGroupId is null
    private function createRequestStackWithCollectionGroup(?int $collectionGroupId): RequestStack
    {
        $request = new Request(null !== $collectionGroupId ? ['collectionGroup' => $collectionGroupId] : []);

        $requestStack = new RequestStack([$request]);

        return $requestStack;
    }

    // Given collaborators over defaults, rather than one "??" per parameter: each of those counts as branching and put this factory over the complexity threshold without holding a single branch
    private function createController(
        ?CollectionItemRepository $collectionItemRepository = null,
        ?CollectionGroupRepository $collectionGroupRepository = null,
        ?SluggerInterface $slugger = null,
        ?AdminUrlGenerator $adminUrlGenerator = null,
        ?RequestStack $requestStack = null,
    ): CollectionItemCrudController {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_EDITOR');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $given = array_filter([
            'slugger' => $slugger,
            'collectionItemRepository' => $collectionItemRepository,
            'collectionGroupRepository' => $collectionGroupRepository,
            'adminUrlGenerator' => $adminUrlGenerator,
            'requestStack' => $requestStack,
        ]);

        $collaborators = $given + [
            'slugger' => new AsciiSlugger(),
            'collectionItemRepository' => $this->createStub(CollectionItemRepository::class),
            'collectionGroupRepository' => $this->createStub(CollectionGroupRepository::class),
            'adminUrlGenerator' => $this->createAdminUrlGenerator(),
            'requestStack' => new RequestStack(),
        ];

        return new CollectionItemCrudController(
            $configService,
            $translator,
            $collaborators['slugger'],
            $collaborators['collectionItemRepository'],
            $collaborators['collectionGroupRepository'],
            $collaborators['adminUrlGenerator'],
            $collaborators['requestStack'],
        );
    }

    private function invokePrivate(CollectionItemCrudController $controller, string $method, array $args = []): mixed
    {
        return new \ReflectionMethod($controller, $method)->invoke($controller, ...$args);
    }

    private function withId(object $entity, int $id): object
    {
        new \ReflectionProperty($entity::class, 'id')->setValue($entity, $id);

        return $entity;
    }

    private function collectionGroupRepositoryReturning(?CollectionGroup $collectionGroup, int $id): CollectionGroupRepository
    {
        $repository = $this->createMock(CollectionGroupRepository::class);
        $repository->expects($this->once())->method('find')->with($id)->willReturn($collectionGroup);

        return $repository;
    }

    // --- currentCollectionGroup (private) --------------------------------------------------------------

    public function testCurrentCollectionGroupReturnsNullWhenThereIsNoRequest(): void
    {
        $controller = $this->createController(requestStack: new RequestStack());

        $this->assertNull($this->invokePrivate($controller, 'currentCollectionGroup'));
    }

    public function testCurrentCollectionGroupReturnsNullWhenNoCollectionGroupQueryParam(): void
    {
        $controller = $this->createController(requestStack: $this->createRequestStackWithCollectionGroup(null));

        $this->assertNull($this->invokePrivate($controller, 'currentCollectionGroup'));
    }

    public function testCurrentCollectionGroupResolvesTheQueryParamThroughTheRepository(): void
    {
        $collectionGroup = $this->withId(new CollectionGroup(), 5);

        $controller = $this->createController(
            collectionGroupRepository: $this->collectionGroupRepositoryReturning($collectionGroup, 5),
            requestStack: $this->createRequestStackWithCollectionGroup(5),
        );

        $this->assertSame($collectionGroup, $this->invokePrivate($controller, 'currentCollectionGroup'));
    }

    // --- configureActions --------------------------------------------------------------------------------

    public function testConfigureActionsBuildsWithoutError(): void
    {
        $actions = $this->createController()->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::NEW)
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        );

        $this->assertInstanceOf(Actions::class, $actions);
    }

    // Detail adds no information beyond what edit already shows - disabled entirely, and a Cancel action lets the admin back out of a create/edit without saving
    public function testConfigureActionsDisablesDetailAndAddsCancelOnNewAndEdit(): void
    {
        $actions = $this->createController()->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::NEW)
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        );

        $this->assertContains(Action::DETAIL, $actions->getAsDto(null)->getDisabledActions());
        $this->assertNotNull($actions->getAsDto(Crud::PAGE_NEW)->getAction(Crud::PAGE_NEW, 'cancel'));
        $this->assertNotNull($actions->getAsDto(Crud::PAGE_EDIT)->getAction(Crud::PAGE_EDIT, 'cancel'));
    }

    // --- createEntity / persistEntity / updateEntity -------------------------------------------------

    // Set here, not in persistEntity(): EasyAdmin validates the entity createEntity() built
    public function testCreateEntitySetsTheCurrentCollectionGroup(): void
    {
        $collectionGroup = $this->withId(new CollectionGroup(), 5);

        $controller = $this->createController(
            collectionGroupRepository: $this->collectionGroupRepositoryReturning($collectionGroup, 5),
            requestStack: $this->createRequestStackWithCollectionGroup(5),
        );

        $item = $controller->createEntity(CollectionItem::class);

        $this->assertSame($collectionGroup, $item->getCollectionGroup());
    }

    public function testPersistEntitySlugifiesAndDelegatesToParentWithoutTouchingTheCollectionGroup(): void
    {
        $collectionGroup = $this->withId(new CollectionGroup(), 5);
        $controller = $this->createController();
        $item = new CollectionItem()->setCollectionGroup($collectionGroup)->setSlug('Néw Ïtem!');

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->once())->method('persist')->with($item);
        $manager->expects($this->once())->method('flush');

        $controller->persistEntity($manager, $item);

        $this->assertSame($collectionGroup, $item->getCollectionGroup());
        $this->assertSame('new-item', $item->getSlug());
    }

    public function testUpdateEntitySlugifiesAndDelegatesToParentWithoutTouchingTheCollectionGroup(): void
    {
        $collectionGroup = new CollectionGroup();
        $controller = $this->createController();
        $item = new CollectionItem()->setCollectionGroup($collectionGroup)->setSlug('Héllo Wörld!');

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->once())->method('persist')->with($item);
        $manager->expects($this->once())->method('flush');

        $controller->updateEntity($manager, $item);

        $this->assertSame($collectionGroup, $item->getCollectionGroup());
        $this->assertSame('hello-world', $item->getSlug());
    }

    // --- slugifyItem (private) ----------------------------------------------------------------------

    public function testSlugifyItemNormalizesAccentsSpacesAndCase(): void
    {
        $controller = $this->createController();
        $item = new CollectionItem()->setCollectionGroup(new CollectionGroup())->setSlug('Héllo Wörld!');

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

    // Collision check is scoped to the item's own collection - a same-named slug in another collection never collides
    public function testSlugifyItemAppendsASuffixOnCollisionWithinTheSameCollectionGroup(): void
    {
        $collectionGroup = $this->withId(new CollectionGroup(), 1);

        $repository = $this->createStub(CollectionItemRepository::class);
        $repository->method('findOneByCollectionGroupAndSlug')->willReturnMap([
            [$collectionGroup, 'my-item', $this->withId(new CollectionItem(), 1)],
            [$collectionGroup, 'my-item-2', null],
        ]);

        $controller = $this->createController($repository);
        $item = new CollectionItem()->setCollectionGroup($collectionGroup)->setSlug('My Item');

        $this->invokePrivate($controller, 'slugifyItem', [$item]);

        $this->assertSame('my-item-2', $item->getSlug());
    }

    // Re-saving an item with its own unchanged slug must not collide with itself
    public function testSlugifyItemDoesNotCollideWithItself(): void
    {
        $collectionGroup = new CollectionGroup();

        $repository = $this->createStub(CollectionItemRepository::class);
        $repository->method('findOneByCollectionGroupAndSlug')->willReturn($this->withId(
            new CollectionItem()->setCollectionGroup($collectionGroup)->setSlug('my-item'),
            5
        ));

        $controller = $this->createController($repository);
        $item = $this->withId(new CollectionItem()->setCollectionGroup($collectionGroup)->setSlug('My Item'), 5);

        $this->invokePrivate($controller, 'slugifyItem', [$item]);

        $this->assertSame('my-item', $item->getSlug());
    }

    public function testSlugifyItemNeverCollidesWhenTheItemHasNoCollectionGroupYet(): void
    {
        $controller = $this->createController();
        $item = new CollectionItem()->setSlug('My Item');

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
        $collectionGroup = $this->withId(new CollectionGroup(), 9);
        $item1 = $this->withId(new CollectionItem()->setCollectionGroup($collectionGroup), 1);
        $item2 = $this->withId(new CollectionItem()->setCollectionGroup($collectionGroup), 2);
        $item3 = $this->withId(new CollectionItem()->setCollectionGroup($collectionGroup), 3);

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
            $this->createReorderRequest(['group' => 9, 'ids' => [3, 1, 2], '_token' => 'valid-token']),
            $entityManager,
        );

        $this->assertSame(0, $item3->getPosition());
        $this->assertSame(1, $item1->getPosition());
        $this->assertSame(2, $item2->getPosition());
        $this->assertSame(['3' => 0, '1' => 1, '2' => 2], json_decode($response->getContent(), true)['positions']);
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
            $this->createReorderRequest(['group' => 9, 'ids' => [1], '_token' => 'invalid-token']),
            $this->createStub(EntityManagerInterface::class),
        );
    }

    // A row deleted between the rendering of the index and the drop: its id comes back with the payload, but a position it never received would be written into its cell by ea-index-sort.js
    public function testReorderAnswersOnlyThePositionsItPersisted(): void
    {
        $collectionGroup = $this->withId(new CollectionGroup(), 9);
        $item = $this->withId(new CollectionItem()->setCollectionGroup($collectionGroup), 1);

        $repository = $this->createStub(CollectionItemRepository::class);
        $repository->method('findBy')->willReturn([$item]);

        $controller = $this->createController($repository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
        ]));

        $response = $controller->reorder(
            $this->createReorderRequest(['group' => 9, 'ids' => [1, 7], '_token' => 'valid-token']),
            $this->createStub(EntityManagerInterface::class),
        );

        $this->assertSame(['1' => 0], json_decode($response->getContent(), true)['positions']);
    }

    // An id whose item doesn't belong to the submitted group never reaches setPosition() - a tampered payload could otherwise reorder another collection's items
    public function testReorderDeniesAccessWhenAnItemDoesNotBelongToTheSubmittedGroup(): void
    {
        $this->expectException(AccessDeniedException::class);

        $otherCollectionGroup = $this->withId(new CollectionGroup(), 42);
        $item = $this->withId(new CollectionItem()->setCollectionGroup($otherCollectionGroup), 1);

        $repository = $this->createStub(CollectionItemRepository::class);
        $repository->method('findBy')->willReturn([$item]);

        $controller = $this->createController($repository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
        ]));

        $controller->reorder(
            $this->createReorderRequest(['group' => 9, 'ids' => [1], '_token' => 'valid-token']),
            $this->createStub(EntityManagerInterface::class),
        );
    }
}
