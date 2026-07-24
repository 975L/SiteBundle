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
use c975L\SiteBundle\Controller\Management\CollectionCrudController;
use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Repository\CollectionGroupRepository;
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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class CollectionCrudControllerTest extends TestCase
{
    // AdminUrlGenerator is final - can't be mocked, so it's built for real with stubbed interface collaborators, same pattern as CollectionItemCrudControllerTest
    private function createAdminUrlGenerator(): AdminUrlGenerator
    {
        $adminControllers = $this->createStub(AdminControllerRegistryInterface::class);
        $adminControllers->method('getDashboardCount')->willReturn(1);
        $adminControllers->method('getFirstDashboard')->willReturn('App\\Controller\\Management\\DashboardController');
        $adminControllers->method('getFirstDashboardRoute')->willReturn('admin');

        $routeGenerator = $this->createStub(AdminRouteGeneratorInterface::class);
        $routeGenerator->method('findRouteName')->willReturn('admin');

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturn('/management/collections');

        return new AdminUrlGenerator(
            $this->createStub(AdminContextProviderInterface::class),
            $urlGenerator,
            $adminControllers,
            $routeGenerator,
            new ArrayAdapter(),
        );
    }

    private function createController(
        ?CollectionGroupRepository $collectionGroupRepository = null,
        ?SluggerInterface $slugger = null,
    ): CollectionCrudController {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_EDITOR');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new CollectionCrudController(
            $configService,
            $translator,
            $slugger ?? new AsciiSlugger(),
            $collectionGroupRepository ?? $this->createStub(CollectionGroupRepository::class),
            $this->createAdminUrlGenerator(),
        );
    }

    private function invokePrivate(CollectionCrudController $controller, string $method, array $args = []): mixed
    {
        return (new \ReflectionMethod($controller, $method))->invoke($controller, ...$args);
    }

    private function withId(CollectionGroup $collectionGroup, int $id): CollectionGroup
    {
        (new \ReflectionProperty(CollectionGroup::class, 'id'))->setValue($collectionGroup, $id);

        return $collectionGroup;
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

    // Detail adds no information beyond what edit already shows - disabled entirely, and a Cancel action lets the admin back out of a create/edit without saving
    public function testConfigureActionsDisablesDetailAndAddsCancelOnNewAndEdit(): void
    {
        $actions = $this->createController()->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        );

        $this->assertContains(Action::DETAIL, $actions->getAsDto(null)->getDisabledActions());
        $this->assertNotNull($actions->getAsDto(Crud::PAGE_NEW)->getAction(Crud::PAGE_NEW, 'cancel'));
        $this->assertNotNull($actions->getAsDto(Crud::PAGE_EDIT)->getAction(Crud::PAGE_EDIT, 'cancel'));
    }

    // The "items" row action is what lets an admin go from "created a collection" to "adding content to it"
    public function testConfigureActionsAddsAnItemsActionOnIndex(): void
    {
        $actions = $this->createController()->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
        );

        $this->assertNotNull($actions->getAsDto(Crud::PAGE_INDEX)->getAction(Crud::PAGE_INDEX, 'items'));
    }

    // --- persistEntity / updateEntity ----------------------------------------------------------------

    public function testPersistEntitySlugifiesAndDelegatesToParent(): void
    {
        $controller = $this->createController();
        $collectionGroup = (new CollectionGroup())->setName('Néw Collection!')->setSlug('Néw Collection!');

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->once())->method('persist')->with($collectionGroup);
        $manager->expects($this->once())->method('flush');

        $controller->persistEntity($manager, $collectionGroup);

        $this->assertSame('new-collection', $collectionGroup->getSlug());
    }

    public function testUpdateEntitySlugifiesAndDelegatesToParent(): void
    {
        $controller = $this->createController();
        $collectionGroup = (new CollectionGroup())->setName('Héllo Wörld!')->setSlug('Héllo Wörld!');

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->once())->method('persist')->with($collectionGroup);
        $manager->expects($this->once())->method('flush');

        $controller->updateEntity($manager, $collectionGroup);

        $this->assertSame('hello-world', $collectionGroup->getSlug());
    }

    // --- slugifyGroup (private) ---------------------------------------------------------------------

    public function testSlugifyGroupNormalizesAccentsSpacesAndCase(): void
    {
        $controller = $this->createController();
        $collectionGroup = (new CollectionGroup())->setSlug('Héllo Wörld!');

        $this->invokePrivate($controller, 'slugifyGroup', [$collectionGroup]);

        $this->assertSame('hello-world', $collectionGroup->getSlug());
    }

    public function testSlugifyGroupDoesNothingWhenSlugIsNull(): void
    {
        $controller = $this->createController();
        $collectionGroup = new CollectionGroup();

        $this->invokePrivate($controller, 'slugifyGroup', [$collectionGroup]);

        $this->assertNull($collectionGroup->getSlug());
    }

    // Unique site-wide, unlike CollectionItem's own slug which is scoped per collection
    public function testSlugifyGroupAppendsASuffixOnCollision(): void
    {
        $repository = $this->createStub(CollectionGroupRepository::class);
        $repository->method('findOneBySlug')->willReturnMap([
            ['my-collection', $this->withId(new CollectionGroup(), 1)],
            ['my-collection-2', null],
        ]);

        $controller = $this->createController($repository);
        $collectionGroup = (new CollectionGroup())->setSlug('My Collection');

        $this->invokePrivate($controller, 'slugifyGroup', [$collectionGroup]);

        $this->assertSame('my-collection-2', $collectionGroup->getSlug());
    }

    // Re-saving a collection with its own unchanged slug must not collide with itself
    public function testSlugifyGroupDoesNotCollideWithItself(): void
    {
        $repository = $this->createStub(CollectionGroupRepository::class);
        $repository->method('findOneBySlug')->willReturn($this->withId(
            (new CollectionGroup())->setSlug('my-collection'),
            5
        ));

        $controller = $this->createController($repository);
        $collectionGroup = $this->withId((new CollectionGroup())->setSlug('My Collection'), 5);

        $this->invokePrivate($controller, 'slugifyGroup', [$collectionGroup]);

        $this->assertSame('my-collection', $collectionGroup->getSlug());
    }
}
