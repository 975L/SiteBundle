<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Controller\Management;

use c975L\ConfigBundle\Entity\Redirect;
use c975L\ConfigBundle\Repository\RedirectRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\Export\ContentExporter;
use c975L\ConfigBundle\Service\Export\ExportFormat;
use c975L\ConfigBundle\Service\Export\TableExporter;
use c975L\SiteBundle\Controller\Management\PageCrudController;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Form\Type\PageHealthCheckPanelType;
use c975L\SiteBundle\Form\Type\PageQrCodeType;
use c975L\SiteBundle\Management\PageExportProvider;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Management\BlockDataExporter;
use c975L\UiBundle\Service\BlockMoveRowAttrBuilder;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\UnitOfWork;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Config\Option\EA;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Orm\EntityRepositoryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Registry\AdminControllerRegistryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Router\AdminRouteGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatableInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// App\Entity\User (the type Page::setUser() actually requires) belongs to the consuming application, not to this standalone bundle checkout - so Security::getUser() is always stubbed to null here, covering the "nobody logged in" branch only, same limitation as UiBundle's BlockUserListenerTest
class PageCrudControllerTest extends TestCase
{
    private function createContainer(array $services): Container
    {
        $container = new Container();
        foreach ($services as $id => $service) {
            $container->set($id, $service);
        }

        return $container;
    }

    private function createAuthorizationChecker(bool $granted): AuthorizationCheckerInterface
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn($granted);

        return $checker;
    }

    // addFlash() needs a session-backed request_stack service
    private function createRequestStackWithSession(): RequestStack
    {
        $session = new Session(new MockArraySessionStorage());
        $request = new Request();
        $request->setSession($session);

        $requestStack = new RequestStack([$request]);

        return $requestStack;
    }

    // AdminUrlGenerator is final - can't be mocked, so it's built for real with stubbed interface collaborators, matching how the framework itself wires it
    private function createAdminUrlGenerator(string $generatedUrl = '/management/pages'): AdminUrlGenerator
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

    // AdminContextProvider is final too, but trivial enough (just reads a request attribute) to build for real instead - avoids needing to mock it
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

    private function createController(
        ?Security $security = null,
        ?ConfigServiceInterface $configService = null,
        ?AdminUrlGenerator $adminUrlGenerator = null,
        ?TranslatorInterface $translator = null,
        ?RedirectRepository $redirectRepository = null,
        ?PageRepository $pageRepository = null,
        ?AdminContextProvider $adminContextProvider = null,
        ?RequestStack $requestStack = null,
        ?SluggerInterface $slugger = null,
        ?Connection $connection = null,
        ?TableExporter $tableExporter = null,
        ?ContentExporter $contentExporter = null,
        ?PageExportProvider $pageExportProvider = null,
        ?BlockMoveRowAttrBuilder $blockMoveRowAttrBuilder = null,
    ): PageCrudController {
        $translatorStub = $translator ?? $this->createStub(TranslatorInterface::class);
        if (null === $translator) {
            $translatorStub->method('trans')->willReturnArgument(0);
        }

        $pageRepository ??= $this->createStub(PageRepository::class);

        return new PageCrudController(
            $security ?? $this->createStub(Security::class),
            $configService ?? $this->createConfigService(),
            $adminUrlGenerator ?? $this->createAdminUrlGenerator(),
            $translatorStub,
            $redirectRepository ?? $this->createStub(RedirectRepository::class),
            $pageRepository,
            $adminContextProvider ?? $this->createAdminContextProvider(),
            $requestStack ?? new RequestStack(),
            $slugger ?? new AsciiSlugger(),
            $connection ?? $this->createStub(Connection::class),
            $tableExporter ?? $this->createStub(TableExporter::class),
            $contentExporter ?? $this->createStub(ContentExporter::class),
            $pageExportProvider ?? new PageExportProvider($pageRepository, new BlockDataExporter(sys_get_temp_dir())),
            $blockMoveRowAttrBuilder ?? $this->createBlockMoveRowAttrBuilder(),
            $this->createCsrfTokenManager(true),
        );
    }

    private function createConfigService(): ConfigServiceInterface
    {
        $service = $this->createStub(ConfigServiceInterface::class);
        $service->method('get')->willReturn('ROLE_EDITOR');

        return $service;
    }

    private function invokePrivate(PageCrudController $controller, string $method, array $args = []): mixed
    {
        return new \ReflectionMethod($controller, $method)->invoke($controller, ...$args);
    }

    private function createAdminContext(Page $page): AdminContext
    {
        $entityDto = new EntityDto(Page::class, new ClassMetadata(Page::class), null, $page);

        return AdminContext::forTesting(crudContext: CrudContext::forTesting(entityDto: $entityDto));
    }

    // --- persistEntity ------------------------------------------------------------------------------

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

    public function testPersistEntitySetsDatesSlugifiesAndDelegatesToParent(): void
    {
        $controller = $this->createController();
        $page = new Page()->setTitle('New Page')->setSlug('Néw Pägé!');

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->once())->method('persist')->with($page);
        $manager->expects($this->once())->method('flush');

        $controller->persistEntity($manager, $page);

        $this->assertNotNull($page->getCreation());
        $this->assertNotNull($page->getModification());
        $this->assertSame('new-page', $page->getSlug());
    }

    public function testPersistEntityLeavesUserNullWhenNobodyIsLoggedIn(): void
    {
        $controller = $this->createController();
        $page = new Page()->setTitle('New Page')->setSlug('new-page');

        $controller->persistEntity($this->createStub(EntityManagerInterface::class), $page);

        $this->assertNull($page->getUser());
    }

    // --- updateEntity --------------------------------------------------------------------------------

    private function createManagerWithOriginalData(array $originalData): EntityManagerInterface
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn($originalData);

        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('getUnitOfWork')->willReturn($unitOfWork);

        return $manager;
    }

    public function testUpdateEntitySetsModificationDateAndDelegatesToParent(): void
    {
        $page = new Page()->setTitle('Same Title')->setSlug('same-title');

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn(['slug' => 'same-title', 'title' => 'Same Title']);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getUnitOfWork')->willReturn($unitOfWork);
        $manager->expects($this->once())->method('persist')->with($page);
        $manager->expects($this->once())->method('flush');

        $this->createController()->updateEntity($manager, $page);

        $this->assertNotNull($page->getModification());
    }

    // Resyncs the slug from the new title, mirroring SlugField's own JS behavior server-side (see the "title-confirm" Stimulus controller referenced in configureFields)
    public function testUpdateEntityResyncsSlugWhenTitleChanges(): void
    {
        $page = new Page()->setTitle('Renamed Page')->setSlug('old-title');

        $manager = $this->createManagerWithOriginalData(['slug' => 'old-title', 'title' => 'Old Title']);

        $this->createController()->updateEntity($manager, $page);

        $this->assertSame('renamed-page', $page->getSlug());
    }

    // The home page's slug is fixed regardless of title changes (see isHomePage in configureFields)
    public function testUpdateEntityDoesNotResyncSlugForHomePage(): void
    {
        $page = new Page()->setTitle('Renamed Home')->setSlug('home');

        $manager = $this->createManagerWithOriginalData(['slug' => 'home', 'title' => 'Old Home Title']);

        $this->createController()->updateEntity($manager, $page);

        $this->assertSame('home', $page->getSlug());
    }

    public function testUpdateEntityCreatesARedirectWhenSlugChanges(): void
    {
        $page = new Page()->setTitle('Same Title')->setSlug('new-slug')->setIsPublished(true);

        $manager = $this->createManagerWithOriginalData(['slug' => 'old-slug', 'title' => 'Same Title', 'isPublished' => true]);
        // parent::updateEntity() also persists the Page itself - capture every persisted entity rather than asserting a single call, since a Redirect is persisted in addition to it
        $persisted = [];
        $manager->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $redirectRepository = $this->createStub(RedirectRepository::class);
        $redirectRepository->method('findOneByFromPath')->willReturn(null);
        $redirectRepository->method('findByToUrl')->willReturn([]);

        $this->createController(redirectRepository: $redirectRepository)->updateEntity($manager, $page);

        $redirects = array_values(array_filter($persisted, static fn (object $e): bool => $e instanceof Redirect));
        $this->assertCount(1, $redirects);
        $this->assertSame('/pages/old-slug', $redirects[0]->getFromPath());
        $this->assertSame('/pages/new-slug', $redirects[0]->getToUrl());
    }

    // An unpublished page, typically a fresh duplicate, leaves no url behind to redirect from
    public function testUpdateEntityDoesNotCreateARedirectWhenPageWasNotPublished(): void
    {
        $page = new Page()->setTitle('Renamed Page')->setSlug('old-page-copy');

        $manager = $this->createManagerWithOriginalData(['slug' => 'old-page-copy', 'title' => 'Old Page (copy)', 'isPublished' => false]);
        $persisted = [];
        $manager->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $this->createController()->updateEntity($manager, $page);

        $this->assertSame('renamed-page', $page->getSlug());
        $this->assertCount(1, $persisted);
        $this->assertInstanceOf(Page::class, $persisted[0]);
    }

    // Renamed and published in one save: the state before the save is what counts
    public function testUpdateEntityDoesNotCreateARedirectWhenPageIsRenamedAndPublishedAtOnce(): void
    {
        $page = new Page()->setTitle('Renamed Page')->setSlug('old-page-copy')->setIsPublished(true);

        $manager = $this->createManagerWithOriginalData(['slug' => 'old-page-copy', 'title' => 'Old Page (copy)', 'isPublished' => false]);
        $persisted = [];
        $manager->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $this->createController()->updateEntity($manager, $page);

        $this->assertCount(1, $persisted);
        $this->assertInstanceOf(Page::class, $persisted[0]);
    }

    public function testUpdateEntityDoesNotCreateARedirectWhenSlugIsUnchanged(): void
    {
        $page = new Page()->setTitle('Same Title')->setSlug('same-slug');

        $manager = $this->createManagerWithOriginalData(['slug' => 'same-slug', 'title' => 'Same Title']);
        $persisted = [];
        $manager->method('persist')->willReturnCallback(function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $this->createController()->updateEntity($manager, $page);

        // Only the Page itself (via parent::updateEntity()), no Redirect
        $this->assertCount(1, $persisted);
        $this->assertInstanceOf(Page::class, $persisted[0]);
    }

    public function testUpdateEntityLeavesUserNullWhenNobodyIsLoggedIn(): void
    {
        $page = new Page()->setTitle('Same Title')->setSlug('same-title');
        $manager = $this->createManagerWithOriginalData(['slug' => 'same-title', 'title' => 'Same Title']);

        $this->createController()->updateEntity($manager, $page);

        $this->assertNull($page->getUser());
    }

    // --- deleteEntity (move to trash) -----------------------------------------------------------------

    public function testDeleteEntityMovesPageToTrashWithoutRemovingIt(): void
    {
        $page = new Page()->setTitle('Old Page')->setSlug('old-page')->setIsPublished(true);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->never())->method('remove');
        $manager->expects($this->once())->method('flush');

        $this->createController()->deleteEntity($manager, $page);

        $this->assertTrue($page->isDeleted());
        $this->assertFalse($page->isPublished());
        $this->assertNotNull($page->getModification());
    }

    // A page in the trash must never stay referenced: the sitemap, llms.txt and the crawlers would keep pointing at a url that now answers 410. Carried by the unpublishing above, which Page::unreferenceWhenUnpublished() turns into an unreferencing at that very flush - played here as Doctrine would
    public function testDeleteEntityUnreferencesTheTrashedPage(): void
    {
        $page = new Page()->setTitle('Old Page')->setSlug('old-page')->setIsPublished(true)->setIsIndexable(true);

        $manager = $this->createStub(EntityManagerInterface::class);

        $this->createController()->deleteEntity($manager, $page);
        $page->unreferenceWhenUnpublished();

        $this->assertFalse($page->isIndexable());
    }

    // --- duplicate -------------------------------------------------------------------------------------

    public function testDuplicateDeniesAccessBelowEditor(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(false),
        ]));

        $controller->duplicate($this->createAdminContext(new Page()), $this->createStub(EntityManagerInterface::class));
    }

    public function testDuplicateClonesPageTitleSlugAndContent(): void
    {
        $source = new Page()
            ->setTitle('Original')
            ->setSlug('original')
            ->setSummarySocialNetwork('summary')
            ->setPriority(5)
            ->setChangeFrequency('weekly')
            ->setIsPublished(true);

        $block = new Block()->setKind('article')->setPosition(0)->setData(['title' => 'Hello'])->setAnimation('fade');
        $source->addBlock($block);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findOneBy')->willReturn(null);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->once())->method('persist')->with($this->isInstanceOf(Page::class));
        $manager->expects($this->once())->method('flush');

        $controller = $this->createController(pageRepository: $pageRepository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'request_stack' => $this->createRequestStackWithSession(),
        ]));

        $response = $controller->duplicate($this->createAdminContext($source), $manager);

        $this->assertSame(302, $response->getStatusCode());
    }

    public function testDuplicateNeverPublishesTheCopy(): void
    {
        $source = new Page()->setTitle('Original')->setSlug('original')->setIsPublished(true);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findOneBy')->willReturn(null);

        $capturedCopy = null;
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('persist')->willReturnCallback(function (object $entity) use (&$capturedCopy): void {
            $capturedCopy = $entity;
        });

        $controller = $this->createController(pageRepository: $pageRepository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'request_stack' => $this->createRequestStackWithSession(),
        ]));

        $controller->duplicate($this->createAdminContext($source), $manager);

        $this->assertInstanceOf(Page::class, $capturedCopy);
        $this->assertFalse($capturedCopy->isPublished());
    }

    public function testDuplicateClonesEachBlockWithItsOwnMedias(): void
    {
        $source = new Page()->setTitle('Original')->setSlug('original');
        $media = new Media()->setAlt('alt text')->setLabel('caption');
        $block = new Block()->setKind('article')->setData(['title' => 'x']);
        $block->addMedia($media);
        $source->addBlock($block);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findOneBy')->willReturn(null);

        $capturedCopy = null;
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('persist')->willReturnCallback(function (object $entity) use (&$capturedCopy): void {
            $capturedCopy = $entity;
        });

        $controller = $this->createController(pageRepository: $pageRepository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'request_stack' => $this->createRequestStackWithSession(),
        ]));

        $controller->duplicate($this->createAdminContext($source), $manager);

        $copiedBlock = $capturedCopy->getBlocks()->first();
        $this->assertNotSame($block, $copiedBlock);
        $this->assertSame('article', $copiedBlock->getKind());
        $copiedMedia = $copiedBlock->getMedias()->first();
        $this->assertNotSame($media, $copiedMedia);
        $this->assertSame('alt text', $copiedMedia->getAlt());
    }

    // A block hidden on the original stays hidden on the copy: duplicating a page is meant to hand back the same page, not to republish what the editor had put aside
    public function testDuplicateKeepsABlockHidden(): void
    {
        $source = new Page()->setTitle('Original')->setSlug('original');
        $source->addBlock(new Block()->setKind('article')->setHidden(true));

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findOneBy')->willReturn(null);

        $capturedCopy = null;
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('persist')->willReturnCallback(function (object $entity) use (&$capturedCopy): void {
            $capturedCopy = $entity;
        });

        $controller = $this->createController(pageRepository: $pageRepository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'request_stack' => $this->createRequestStackWithSession(),
        ]));

        $controller->duplicate($this->createAdminContext($source), $manager);

        $this->assertTrue($capturedCopy->getBlocks()->first()->isHidden());
    }

    // A container block (columns...) holds its content in child blocks, nested to any depth - the copy must carry the whole tree, not just the container
    public function testDuplicateClonesNestedSlotsAndTheirContent(): void
    {
        $source = new Page()->setTitle('Original')->setSlug('original');
        $inner = new Block()->setKind('article')->setData(['title' => 'inner']);
        $column = new Block()->setKind('column')->setData(['width' => '6']);
        $column->addSlot($inner);
        $container = new Block()->setKind('columns');
        $container->addSlot($column);
        $source->addBlock($container);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findOneBy')->willReturn(null);

        $capturedCopy = null;
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('persist')->willReturnCallback(function (object $entity) use (&$capturedCopy): void {
            $capturedCopy = $entity;
        });

        $controller = $this->createController(pageRepository: $pageRepository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'request_stack' => $this->createRequestStackWithSession(),
        ]));

        $controller->duplicate($this->createAdminContext($source), $manager);

        $copiedContainer = $capturedCopy->getBlocks()->first();
        $copiedColumn = $copiedContainer->getSlots()->first();
        $this->assertNotSame($column, $copiedColumn);
        $this->assertSame('column', $copiedColumn->getKind());
        $this->assertSame(['width' => '6'], $copiedColumn->getData());
        $this->assertSame($copiedContainer, $copiedColumn->getParentBlock());

        $copiedInner = $copiedColumn->getSlots()->first();
        $this->assertNotSame($inner, $copiedInner);
        $this->assertSame(['title' => 'inner'], $copiedInner->getData());
        $this->assertSame($copiedColumn, $copiedInner->getParentBlock());
    }

    // --- publishAsReplacement ------------------------------------------------------------------------

    public function testPublishAsReplacementDeniesAccessBelowEditor(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(false),
        ]));

        $controller->publishAsReplacement(
            $this->createAdminContext(new Page()),
            $this->createStub(EntityManagerInterface::class)
        );
    }

    // The original is looked up by id (not slug, since a concurrent draft's own publishAsReplacement() may have since changed it), its slug is archived first (own flush) so the unique constraint on slug is never violated, then the copy takes it over, gets published, and "replaces" is cleared
    public function testPublishAsReplacementSwapsSlugsPublishesCopyAndTrashesOriginal(): void
    {
        $original = new Page()->setTitle('Home')->setSlug('home')->setIsPublished(true);
        new \ReflectionProperty(Page::class, 'id')->setValue($original, 7);
        $copy = new Page()->setTitle('Home (copy)')->setSlug('home-copy')->setReplaces(7);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('find')->willReturn($original);
        $pageRepository->method('findOneBy')->willReturnMap([
            [['slug' => 'home-archived'], null, null],
        ]);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->exactly(2))->method('flush');
        $manager->method('wrapInTransaction')->willReturnCallback(
            static fn (callable $func) => $func()
        );

        $controller = $this->createController(pageRepository: $pageRepository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'request_stack' => $this->createRequestStackWithSession(),
        ]));

        $response = $controller->publishAsReplacement($this->createAdminContext($copy), $manager);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertSame('home-archived', $original->getSlug());
        $this->assertTrue($original->isDeleted());
        $this->assertFalse($original->isPublished());
        $this->assertSame('home', $copy->getSlug());
        $this->assertTrue($copy->isPublished());
        $this->assertNull($copy->getReplaces());
    }

    // The copy takes the original's referencing state over along with its slug, so an indexed url doesn't silently turn noindex the day the page behind it is replaced. The value has to be read before the swap: the first flush unpublishes the original and Page::unreferenceWhenUnpublished() drops its own isIndexable right there - the callback below plays that PreFlush rule on each flush, as Doctrine would
    public function testPublishAsReplacementCarriesOverIsIndexableFromTheOriginal(): void
    {
        $original = new Page()->setTitle('Home')->setSlug('home')->setIsPublished(true)->setIsIndexable(true);
        new \ReflectionProperty(Page::class, 'id')->setValue($original, 7);
        $copy = new Page()->setTitle('Home (copy)')->setSlug('home-copy')->setReplaces(7)->setIsIndexable(false);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('find')->willReturn($original);
        $pageRepository->method('findOneBy')->willReturn(null);

        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('flush')->willReturnCallback(static function () use ($original, $copy): void {
            $original->unreferenceWhenUnpublished();
            $copy->unreferenceWhenUnpublished();
        });
        $manager->method('wrapInTransaction')->willReturnCallback(
            static fn (callable $func) => $func()
        );

        $controller = $this->createController(pageRepository: $pageRepository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'request_stack' => $this->createRequestStackWithSession(),
        ]));

        $controller->publishAsReplacement($this->createAdminContext($copy), $manager);

        $this->assertTrue($copy->isIndexable());
        $this->assertFalse($original->isIndexable());
    }

    // The original may already be gone (deleted/renamed since the copy was created) - aborts safely, flashes an error, never touches the copy
    public function testPublishAsReplacementFlashesErrorWhenOriginalNotFound(): void
    {
        $copy = new Page()->setTitle('Draft')->setSlug('draft')->setReplaces(999);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('find')->willReturn(null);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->never())->method('flush');

        $controller = $this->createController(pageRepository: $pageRepository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'request_stack' => $this->createRequestStackWithSession(),
        ]));

        $response = $controller->publishAsReplacement($this->createAdminContext($copy), $manager);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertFalse($copy->isPublished());
    }

    // A page can't replace itself - only reachable via a crafted/stale "?replaces=<own id>" URL (the dropdown's own displayIf() already hides this option) - without this guard, $original and $copy resolve to the same entity and the two-flush swap would leave it both published and deleted at once
    public function testPublishAsReplacementFlashesErrorWhenTargetIsItself(): void
    {
        $page = new Page()->setTitle('Home')->setSlug('home')->setIsPublished(true);
        new \ReflectionProperty(Page::class, 'id')->setValue($page, 7);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('find')->willReturn($page);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->never())->method('flush');

        $controller = $this->createController(pageRepository: $pageRepository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'request_stack' => $this->createRequestStackWithSession(),
        ]));

        $request = new Request(['replaces' => '7']);
        $response = $controller->publishAsReplacement($this->createAdminContext($page), $manager, $request);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertTrue($page->isPublished());
        $this->assertFalse($page->isDeleted());
    }

    // Two drafts created (via duplicate()) from the same original before either is published: the first publish archives the original (non-null archivedSlug, mangled slug). The second draft's own publishAsReplacement() must not take over that mangled slug - it's treated the same as "original not found" instead of silently publishing under a garbage URL
    public function testPublishAsReplacementFlashesErrorWhenOriginalAlreadyArchivedByAnotherDraft(): void
    {
        $original = new Page()->setTitle('Home')->setSlug('home-archived')->setArchivedSlug('home');
        new \ReflectionProperty(Page::class, 'id')->setValue($original, 7);
        $copy = new Page()->setTitle('Home (copy 2)')->setSlug('home-copy-2')->setReplaces(7);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('find')->willReturn($original);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->never())->method('flush');

        $controller = $this->createController(pageRepository: $pageRepository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'request_stack' => $this->createRequestStackWithSession(),
        ]));

        $response = $controller->publishAsReplacement($this->createAdminContext($copy), $manager);

        $this->assertSame(302, $response->getStatusCode());
        $this->assertFalse($copy->isPublished());
        $this->assertSame('home-copy-2', $copy->getSlug());
    }

    // --- uniqueSlug (private) -------------------------------------------------------------------------

    // --- slugifyPage (private) -------------------------------------------------------------------------

    public function testSlugifyPageNormalizesAccentsSpacesAndCase(): void
    {
        $controller = $this->createController();
        $page = new Page()->setTitle('x')->setSlug('Héllo Wörld!');

        $this->invokePrivate($controller, 'slugifyPage', [$page]);

        $this->assertSame('hello-world', $page->getSlug());
    }

    // --- pagePath / buildPageUrl (private) ---------------------------------------------------------------

    public function testPagePathPointsToPreviewWhenUnpublished(): void
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route, array $params = []) => $route . ':' . ($params['page'] ?? '')
        );

        $controller = $this->createController();
        $controller->setContainer($this->createContainer(['router' => $router]));

        $page = new Page()->setTitle('x')->setSlug('draft-page')->setIsPublished(false);

        $this->assertSame('page_preview:draft-page', $this->invokePrivate($controller, 'pagePath', [$page]));
    }

    public function testPagePathPointsToHomeRouteForTheHomeSlug(): void
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $route) => $route);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer(['router' => $router]));

        $page = new Page()->setTitle('x')->setSlug('home')->setIsPublished(true);

        $this->assertSame('page_home', $this->invokePrivate($controller, 'pagePath', [$page]));
    }

    public function testPagePathPointsToDisplayRouteForARegularPublishedPage(): void
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route, array $params = []) => $route . ':' . ($params['page'] ?? '')
        );

        $controller = $this->createController();
        $controller->setContainer($this->createContainer(['router' => $router]));

        $page = new Page()->setTitle('x')->setSlug('about')->setIsPublished(true);

        $this->assertSame('page_display:about', $this->invokePrivate($controller, 'pagePath', [$page]));
    }

    public function testBuildPageUrlCombinesSiteUrlAndPagePath(): void
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route, array $params = []) => '/' . ($params['page'] ?? $route)
        );

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('https://example.com/');

        $controller = $this->createController(configService: $configService);
        $controller->setContainer($this->createContainer(['router' => $router]));

        $page = new Page()->setTitle('x')->setSlug('about')->setIsPublished(true);

        $this->assertSame('https://example.com/about', $this->invokePrivate($controller, 'buildPageUrl', [$page]));
    }

    // --- configureResponseParameters ---------------------------------------------------------------------

    private function createResponseParameters(string $pageName, ?Page $page = null): KeyValueStore
    {
        return KeyValueStore::new([
            'pageName' => $pageName,
            'entity' => null === $page ? null : new EntityDto(Page::class, new ClassMetadata(Page::class), null, $page),
        ]);
    }

    private function createRouterStub(): UrlGeneratorInterface
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $route, array $params = []) => '/' . ($params['page'] ?? $route)
        );

        return $router;
    }

    public function testConfigureResponseParametersHandsTheEditScreenThePublicPathOfAPublishedPage(): void
    {
        $controller = $this->createController();
        $controller->setContainer($this->createContainer(['router' => $this->createRouterStub()]));

        $page = new Page()->setTitle('x')->setSlug('about')->setIsPublished(true);
        $parameters = $controller->configureResponseParameters($this->createResponseParameters(Crud::PAGE_EDIT, $page));

        $this->assertSame('/about', $parameters->get('page_public_path'));
    }

    // A preview url is reachable by an editor alone, so the note pointing a social network at it is not offered
    public function testConfigureResponseParametersLeavesThePublicPathUnsetForAnUnpublishedPage(): void
    {
        $controller = $this->createController();
        $controller->setContainer($this->createContainer(['router' => $this->createRouterStub()]));

        $page = new Page()->setTitle('x')->setSlug('draft-page')->setIsPublished(false);
        $parameters = $controller->configureResponseParameters($this->createResponseParameters(Crud::PAGE_EDIT, $page));

        $this->assertNull($parameters->get('page_public_path'));
    }

    // A page being created has no url yet, and no screen but the edit one carries the note
    public function testConfigureResponseParametersLeavesThePublicPathUnsetOutsideTheEditScreen(): void
    {
        $controller = $this->createController();
        $controller->setContainer($this->createContainer(['router' => $this->createRouterStub()]));

        $page = new Page()->setTitle('x')->setSlug('about')->setIsPublished(true);
        $parameters = $controller->configureResponseParameters($this->createResponseParameters(Crud::PAGE_NEW, $page));

        $this->assertNull($parameters->get('page_public_path'));
    }

    // --- fetchExportRows (private) -----------------------------------------------------------------------

    public function testFetchExportRowsQueriesTheSitePageTable(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('fetchAllAssociative')
            ->with($this->stringContains('FROM `site_page`'))
            ->willReturn([['slug' => 'about']]);

        $controller = $this->createController(connection: $connection);

        $this->assertSame([['slug' => 'about']], $this->invokePrivate($controller, 'fetchExportRows'));
    }

    // --- configureActions / configureFields / configureFilters / createIndexQueryBuilder ------------------

    // RequestStack simulating being on a given EasyAdmin CRUD page (INDEX/EDIT/DETAIL...) - the "publishAsReplacement" dropdown's own every-non-deleted-page query only ever runs on PAGE_EDIT (see configureActions(), which reads the crudAction request attribute directly since the AdminContext isn't attached to the request yet at that point). It's a route default merged into attributes by Symfony's router itself, not a query param
    private function createRequestStackOnPage(string $pageName): RequestStack
    {
        $request = new Request();
        $request->attributes->set(EA::CRUD_ACTION, $pageName);

        $requestStack = new RequestStack([$request]);

        return $requestStack;
    }

    public function testConfigureActionsBuildsWithoutError(): void
    {
        // A real EasyAdmin runtime pre-populates default actions (EDIT, DELETE...) before calling configureActions() - reorder()/update() below assume EDIT already exists on PAGE_INDEX
        $actions = $this->createController()->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
                ->add(Crud::PAGE_DETAIL, Action::EDIT)
                ->add(Crud::PAGE_DETAIL, Action::DELETE)
        );

        $this->assertInstanceOf(Actions::class, $actions);
    }

    // A Cancel action lets the admin back out of a create/edit without saving, and "Export selection" is a batch action on the index gated by site-role-admin
    public function testConfigureActionsAddsCancelOnNewAndEditAndExportSelectionOnIndex(): void
    {
        $actions = $this->createController()->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
                ->add(Crud::PAGE_DETAIL, Action::EDIT)
                ->add(Crud::PAGE_DETAIL, Action::DELETE)
        );

        $this->assertNotNull($actions->getAsDto(Crud::PAGE_NEW)->getAction(Crud::PAGE_NEW, 'cancel'));
        $this->assertNotNull($actions->getAsDto(Crud::PAGE_EDIT)->getAction(Crud::PAGE_EDIT, 'cancel'));
        $this->assertNotNull($actions->getAsDto(Crud::PAGE_INDEX)->getAction(Crud::PAGE_INDEX, 'exportSelection'));
    }

    // tuneIndexActions()'s own reorder() turns EasyAdmin's automatic ordering off for the whole page, and the batch bar then falls back to the order actions were added in - which puts the dashboard's "batchDelete" ahead of the export, delete reading first
    public function testConfigureActionsPutsTheExportAheadOfDeleteInTheBatchBar(): void
    {
        $actions = $this->createController()->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::BATCH_DELETE)
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
                ->add(Crud::PAGE_DETAIL, Action::EDIT)
                ->add(Crud::PAGE_DETAIL, Action::DELETE)
        );

        $names = array_keys($actions->getAsDto(Crud::PAGE_INDEX)->getActions()->all());

        $this->assertContains('exportSelection', $names);
        $this->assertContains(Action::BATCH_DELETE, $names);
        $this->assertLessThan(array_search(Action::BATCH_DELETE, $names, true), array_search('exportSelection', $names, true));
    }

    // Deleting a page only moves it to the trash, which an editor may do - pulling one back out or removing it for good is what restore()/deletePermanently() deny below site-role-admin, and a button leading to their own 403 is a button not to draw
    public function testConfigureActionsGivesTheTwoTrashActionsTheAdminBarTheirOwnMethodsState(): void
    {
        // One value per key: the stub answering the same thing everywhere would let an action ask for the wrong role unnoticed
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $key): string => match ($key) {
            'site-role-editor' => 'ROLE_EDITOR',
            'site-role-admin' => 'ROLE_ADMIN',
            default => throw new \InvalidArgumentException(sprintf('Unexpected config key "%s"', $key)),
        });

        $permissions = $this->createController(configService: $configService)->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
                ->add(Crud::PAGE_DETAIL, Action::EDIT)
                ->add(Crud::PAGE_DETAIL, Action::DELETE)
        )->getAsDto(Crud::PAGE_INDEX)->getActionPermissions();

        $this->assertSame('ROLE_ADMIN', $permissions['restore']);
        $this->assertSame('ROLE_ADMIN', $permissions['deletePermanently']);
        $this->assertSame('ROLE_ADMIN', $permissions['exportSelection']);
        $this->assertSame('ROLE_EDITOR', $permissions['trash'], 'Moving a page to the trash and going there is the editor\'s own');
        $this->assertSame('ROLE_EDITOR', $permissions[Action::DELETE]);
    }

    // Deleting a page only moves it to the trash (see deleteEntity()), so it must carry its own confirmation message: left at the default "true", EasyAdmin's ActionFactory would show its delete_modal.content, telling the admin the action cannot be undone
    public function testConfigureActionsGivesDeleteATrashConfirmationRatherThanEasyAdminsIrreversibleOne(): void
    {
        $actions = $this->createController()->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
                ->add(Crud::PAGE_DETAIL, Action::EDIT)
                ->add(Crud::PAGE_DETAIL, Action::DELETE)
        );

        foreach ([Crud::PAGE_INDEX, Crud::PAGE_DETAIL] as $pageName) {
            $confirmation = $actions->getAsDto($pageName)->getAction($pageName, Action::DELETE)->getConfirmationMessage();

            $this->assertNotTrue($confirmation, sprintf('The delete action on "%s" still uses EasyAdmin default confirmation.', $pageName));
            $this->assertInstanceOf(TranslatableInterface::class, $confirmation);
            $this->assertSame('confirm.move_to_trash', $confirmation->getMessage());
        }
    }

    // Not on the edit screen: the "publishAsReplacement" dropdown is never shown there (only added to Crud::PAGE_EDIT, see configureActions()), so its every-non-deleted-page query must not even run
    public function testConfigureActionsSkipsPublishAsReplacementQueryWhenNotOnEditPage(): void
    {
        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->expects($this->never())->method('createQueryBuilder');

        $actions = $this->createController(
            pageRepository: $pageRepository,
            requestStack: $this->createRequestStackOnPage(Crud::PAGE_INDEX)
        )->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
                ->add(Crud::PAGE_DETAIL, Action::EDIT)
                ->add(Crud::PAGE_DETAIL, Action::DELETE)
        );

        $this->assertInstanceOf(Actions::class, $actions);
    }

    // On the edit screen, but no other page to offer as a target: the dropdown is built from pageRepository's own query (see configureActions()), which must come back an empty array, not PHPUnit's default null-for-mixed-return-type - otherwise the group would end up with zero actions, which EasyAdmin's ActionGroup rejects
    public function testConfigureActionsBuildsWithoutErrorOnEditPageWithNoOtherPage(): void
    {
        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([]);
        $queryBuilder->method('getQuery')->willReturn($query);
        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $actions = $this->createController(
            pageRepository: $pageRepository,
            requestStack: $this->createRequestStackOnPage(Crud::PAGE_EDIT)
        )->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
                ->add(Crud::PAGE_DETAIL, Action::EDIT)
                ->add(Crud::PAGE_DETAIL, Action::DELETE)
        );

        $this->assertInstanceOf(Actions::class, $actions);
    }

    // On the edit screen, with at least one other page to offer - covers the group actually being built and added, instead of skipped
    public function testConfigureActionsBuildsWithoutErrorWhenAnotherPageExists(): void
    {
        $other = new Page()->setTitle('Other')->setSlug('other');
        new \ReflectionProperty(Page::class, 'id')->setValue($other, 99);

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([$other]);
        $queryBuilder->method('getQuery')->willReturn($query);
        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $actions = $this->createController(
            pageRepository: $pageRepository,
            requestStack: $this->createRequestStackOnPage(Crud::PAGE_EDIT)
        )->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
                ->add(Crud::PAGE_DETAIL, Action::EDIT)
                ->add(Crud::PAGE_DETAIL, Action::DELETE)
        );

        $this->assertInstanceOf(Actions::class, $actions);
    }

    // ActionFactory only gives an ActionDto its default "action-<name>" class, so the group has to state its own - SiteGuidedProjectProvider's revision parcours highlights ".action-publishAsReplacement"
    public function testPublishAsReplacementGroupCarriesItsOwnCssClass(): void
    {
        $other = new Page()->setTitle('Other')->setSlug('other');
        new \ReflectionProperty(Page::class, 'id')->setValue($other, 99);

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn([$other]);
        $queryBuilder->method('getQuery')->willReturn($query);
        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        $actions = $this->createController(
            pageRepository: $pageRepository,
            requestStack: $this->createRequestStackOnPage(Crud::PAGE_EDIT)
        )->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
                ->add(Crud::PAGE_DETAIL, Action::EDIT)
                ->add(Crud::PAGE_DETAIL, Action::DELETE)
        );

        $group = $actions->getAsDto(Crud::PAGE_EDIT)->getActions()['publishAsReplacement'] ?? null;

        $this->assertNotNull($group, 'The publishAsReplacement group is no longer added to the edit screen.');
        $this->assertSame('action-publishAsReplacement', $group->getCssClass());
    }

    public function testConfigureFiltersBuildsWithoutError(): void
    {
        $filters = $this->createController()->configureFilters(Filters::new());

        $this->assertInstanceOf(Filters::class, $filters);
    }

    public function testConfigureFieldsReturnsFieldsWhenThereIsNoAdminContext(): void
    {
        $fields = iterator_to_array($this->createController()->configureFields('index'));

        $this->assertNotEmpty($fields);
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

    // The "title-confirm" Stimulus controller (UiBundle's assets/js/title-confirm.js) reuses EasyAdmin's confirmation modal, which isn't rendered on the "new" crud page (only edit/index/detail) - and there's no existing slug to preserve yet anyway, so the field must stay plain there
    public function testConfigureFieldsDoesNotAddTitleConfirmAttributesOnNewPage(): void
    {
        $fields = $this->createController()->configureFields(Crud::PAGE_NEW);
        $title = $this->findFieldByProperty($fields, 'title');

        $this->assertArrayNotHasKey('data-controller', $title->getAsDto()->getFormTypeOptions()['attr'] ?? []);
    }

    public function testConfigureFieldsAddsTitleConfirmAttributesOnEditPage(): void
    {
        $fields = $this->createController()->configureFields(Crud::PAGE_EDIT);
        $title = $this->findFieldByProperty($fields, 'title');

        $this->assertSame('title-confirm', $title->getAsDto()->getFormTypeOptions()['attr']['data-controller'] ?? null);
    }

    // "sitemap-fields" must sit on the row, not on the checkbox: EasyAdmin renders a BooleanField as a <twig:ea:Switch> component that only forwards id/name/value/checked/disabled/required/variant and silently drops "attr", so a data-controller set there never reaches the DOM and changeFrequency/priority stay enabled
    public function testConfigureFieldsAddsSitemapFieldsControllerOnIsIndexableRow(): void
    {
        $fields = $this->createController()->configureFields(Crud::PAGE_EDIT);
        $isIndexable = $this->findFieldByProperty($fields, 'isIndexable');

        $this->assertSame('sitemap-fields', $isIndexable->getAsDto()->getFormTypeOptions()['row_attr']['data-controller'] ?? null);
        $this->assertArrayNotHasKey('data-controller', $isIndexable->getAsDto()->getFormTypeOptions()['attr'] ?? []);
    }

    // Both switches toggle the entity through ajax on the index, where unpublishing also unreferences (see Page::unreferenceWhenUnpublished()) - "publication-switch" is what keeps the "isIndexable" one from showing a value the database no longer holds. Set as an html attribute: that's what EasyAdmin renders on the index <td>, form type options never reaching it
    public function testConfigureFieldsAddsPublicationSwitchControllerOnIsPublishedCell(): void
    {
        $fields = $this->createController()->configureFields(Crud::PAGE_INDEX);
        $isPublished = $this->findFieldByProperty($fields, 'isPublished');

        $this->assertSame('publication-switch', $isPublished->getAsDto()->getHtmlAttributes()['data-controller'] ?? null);
    }

    // No entity yet (new page) - blockMoveRowAttr() has nothing to key the move on, so the "blocks" field gets no row_attr at all rather than a partial/broken one
    public function testConfigureFieldsBlocksFieldHasNoRowAttrOnNewPage(): void
    {
        $fields = $this->createController()->configureFields(Crud::PAGE_NEW);
        $blocks = $this->findFieldByProperty($fields, 'blocks');

        $this->assertSame([], $blocks->getAsDto()->getFormTypeOptions()['row_attr'] ?? null);
    }

    // Editing an already-saved page - the "blocks" field's row_attr carries what UiBundle's ea-sortable.js/BlockMoveController needs to relocate a Block into/out of a container (see UiBundle's BlockMoveRowAttrBuilder)
    public function testConfigureFieldsBlocksFieldRowAttrCarriesBlockMoveDataOnEditPage(): void
    {
        $page = new Page()->setTitle('x')->setSlug('about');
        new \ReflectionProperty(Page::class, 'id')->setValue($page, 7);

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturn('/admin/ui/block/move');

        $controller = $this->createController(
            adminContextProvider: $this->createAdminContextProvider($this->createAdminContext($page)),
            blockMoveRowAttrBuilder: $this->createBlockMoveRowAttrBuilder(),
        );
        $controller->setContainer($this->createContainer(['router' => $router]));

        $fields = $controller->configureFields(Crud::PAGE_EDIT);
        $rowAttr = $this->findFieldByProperty($fields, 'blocks')->getAsDto()->getFormTypeOptions()['row_attr'] ?? [];

        $this->assertSame('page', $rowAttr['data-ui-move-owner-type']);
        $this->assertSame(7, $rowAttr['data-ui-move-owner-id']);
        $this->assertSame('/admin/ui/block/move', $rowAttr['data-ui-move-url']);
        $this->assertSame('token123', $rowAttr['data-ui-move-csrf-token']);
    }

    // The "Health check" tab and its panel field only make sense once the page exists and has been checked at least once (see PageHealthCheckExtension) - onlyWhenUpdating() keeps both off the "new" page entirely
    public function testConfigureFieldsHealthCheckTabAndPanelAreOnlyDisplayedWhenEditing(): void
    {
        $fields = $this->createController()->configureFields(Crud::PAGE_EDIT);
        $healthCheckField = $this->findFieldByProperty($fields, 'healthCheck');

        $this->assertNotNull($healthCheckField);
        $this->assertTrue($healthCheckField->getAsDto()->isDisplayedOn(Crud::PAGE_EDIT));
        $this->assertFalse($healthCheckField->getAsDto()->isDisplayedOn(Crud::PAGE_NEW));
        $this->assertSame(PageHealthCheckPanelType::class, $healthCheckField->getAsDto()->getFormType());
    }

    // The QR code needs a saved entity id - same onlyWhenUpdating() reasoning as the Health check panel, and it previously only ever rendered on the edit page too (a separate template is used for "new")
    public function testConfigureFieldsQrCodeFieldIsOnlyDisplayedWhenEditing(): void
    {
        $fields = $this->createController()->configureFields(Crud::PAGE_EDIT);
        $qrCodeField = $this->findFieldByProperty($fields, 'qrcode');

        $this->assertNotNull($qrCodeField);
        $this->assertTrue($qrCodeField->getAsDto()->isDisplayedOn(Crud::PAGE_EDIT));
        $this->assertFalse($qrCodeField->getAsDto()->isDisplayedOn(Crud::PAGE_NEW));
        $this->assertSame(PageQrCodeType::class, $qrCodeField->getAsDto()->getFormType());
    }

    // Whether a page is indexed is as telling as whether it is published, so it gets its own column and its own index switch, next to "isPublished"
    public function testConfigureFieldsShowsIsIndexableOnIndex(): void
    {
        $fields = $this->createController()->configureFields(Crud::PAGE_INDEX);
        $isIndexable = $this->findFieldByProperty($fields, 'isIndexable');

        $this->assertNotNull($isIndexable);
        $this->assertTrue($isIndexable->getAsDto()->isDisplayedOn(Crud::PAGE_INDEX));
    }

    // Both columns hold the same value for every row of the trash (see deleteEntity()), and "isIndexable" would stay clickable there with no "isPublished" cell left for the "publication-switch" controller to disable it from
    public function testConfigureFieldsHidesPublicationColumnsInTheTrash(): void
    {
        $requestStack = new RequestStack([new Request(['trash' => 1])]);

        $fields = $this->createController(requestStack: $requestStack)->configureFields(Crud::PAGE_INDEX);

        $this->assertFalse($this->findFieldByProperty($fields, 'isPublished')->getAsDto()->isDisplayedOn(Crud::PAGE_INDEX));
        $this->assertFalse($this->findFieldByProperty($fields, 'isIndexable')->getAsDto()->isDisplayedOn(Crud::PAGE_INDEX));
    }

    public function testCreateIndexQueryBuilderFiltersOutDeletedPagesByDefault(): void
    {
        $requestStack = new RequestStack([new Request()]);

        $repository = $this->createStub(EntityRepositoryInterface::class);
        $queryBuilder = $this->createMock(QueryBuilder::class);
        $queryBuilder->expects($this->once())->method('andWhere')->with('entity.isDeleted = :isDeleted')->willReturnSelf();
        $queryBuilder->expects($this->once())->method('setParameter')->with('isDeleted', false)->willReturnSelf();
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);

        $controller = $this->createController(requestStack: $requestStack);
        $controller->setContainer($this->createContainer([
            EntityRepositoryInterface::class => $repository,
        ]));

        $controller->createIndexQueryBuilder(
            new SearchDto(new Request(), null, null, [], [], null),
            new EntityDto(Page::class, new ClassMetadata(Page::class)),
            new FieldCollection([]),
            new FilterCollection([]),
        );
    }

    // --- deletePermanently leaves a "gone" redirect behind -----------------------------------------------

    private function deletePermanentlyWith(Page $page, EntityManagerInterface $manager, ?Redirect $existing = null, array $pointingAtIt = []): void
    {
        $redirectRepository = $this->createStub(RedirectRepository::class);
        $redirectRepository->method('findByToUrl')->willReturn($pointingAtIt);
        $redirectRepository->method('findOneByFromPath')->willReturn($existing);

        $controller = $this->createController(redirectRepository: $redirectRepository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
            'request_stack' => $this->createRequestStackWithSession(),
        ]));

        $controller->deletePermanently($this->createAdminContext($page), new Request(['token' => 'token']), $manager);
    }

    // The trash's own 410 (see PageController::display()) dies with the row - without this the url would drop back to a plain 404
    public function testDeletePermanentlyPersistsAGoneRedirectForThePageUrl(): void
    {
        $page = new Page()->setTitle('Old')->setSlug('old-page');

        // A stub rather than a mock: what matters is the entity handed to persist(), captured here, not that the call happened
        $persisted = null;
        $manager = $this->createStub(EntityManagerInterface::class);
        $manager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted = $entity;
        });

        $this->deletePermanentlyWith($page, $manager);

        $this->assertInstanceOf(Redirect::class, $persisted);
        $this->assertSame('/pages/old-page', $persisted->getFromPath());
        $this->assertTrue($persisted->isGone());
        $this->assertNull($persisted->getToUrl());
    }

    // A target the admin set up deliberately says more than a dead end, and fromPath is unique anyway
    public function testDeletePermanentlyLeavesAnExistingRedirectOnThatPathAlone(): void
    {
        $page = new Page()->setTitle('Old')->setSlug('old-page');
        $existing = new Redirect()->setFromPath('/pages/old-page')->setToUrl('/pages/bundles');

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->never())->method('persist');

        $this->deletePermanentlyWith($page, $manager, $existing);
    }

    // The urls that redirected to this page led to content just as removed - they answer 410 too, rather than being deleted and dropping back to a 404
    public function testDeletePermanentlyTurnsRedirectsPointingAtThePageIntoGoneOnes(): void
    {
        $page = new Page()->setTitle('Old')->setSlug('old-page');
        $alias = new Redirect()->setFromPath('/pages/legacy-name')->setToUrl('/pages/old-page')->setPermanent(true);

        $manager = $this->createStub(EntityManagerInterface::class);

        $this->deletePermanentlyWith($page, $manager, pointingAtIt: [$alias]);

        $this->assertTrue($alias->isGone());
        $this->assertNull($alias->getToUrl());
        $this->assertSame('/pages/legacy-name', $alias->getFromPath());
    }

    // "home" is served at the site root, which RedirectSubscriber skips by design - a "/pages/home" row would only ever shadow the 301 PageController already answers there
    public function testDeletePermanentlyCreatesNoGoneRedirectForTheHomePage(): void
    {
        $page = new Page()->setTitle('Home')->setSlug('home');

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->never())->method('persist');

        $this->deletePermanentlyWith($page, $manager);
    }

    // --- access-denied smoke tests ---------------------------------------------------------------------

    public function testDeletePermanentlyDeniesAccessBelowAdmin(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(false),
        ]));

        $controller->deletePermanently($this->createAdminContext(new Page()), new Request(['token' => 'token']), $this->createStub(EntityManagerInterface::class));
    }

    public function testRestoreDeniesAccessBelowAdmin(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(false),
        ]));

        $controller->restore($this->createAdminContext(new Page()), new Request(['token' => 'token']), $this->createStub(EntityManagerInterface::class));
    }

    // The action is reached by a GET, so nothing but the token tells a click on the trash screen apart from a request an <img> fired on a logged-in admin
    public function testDeletePermanentlyRemovesNothingWhenCsrfTokenIsInvalid(): void
    {
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->never())->method('remove');
        $manager->expects($this->never())->method('flush');

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(false),
        ]));

        $controller->deletePermanently($this->createAdminContext(new Page()->setTitle('Old')->setSlug('old-page')), new Request(), $manager);
    }

    // Same GET as deletePermanently(), and the same token standing between a click and a forged request
    public function testRestoreLiftsNothingWhenCsrfTokenIsInvalid(): void
    {
        $page = new Page()->setTitle('Old Page')->setSlug('old-page')->setIsDeleted(true);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(false),
        ]));

        $controller->restore($this->createAdminContext($page), new Request(), $this->createStub(EntityManagerInterface::class));

        $this->assertTrue($page->isDeleted());
    }

    // A page archived by publishAsReplacement() reclaims its real slug on restore if nothing else has taken it since, and archivedSlug is cleared
    public function testRestoreReclaimsArchivedSlugWhenFree(): void
    {
        $page = new Page()->setTitle('Home')->setSlug('home-archived')->setArchivedSlug('home')->setIsDeleted(true);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findOneBy')->willReturn(null);

        $controller = $this->createController(pageRepository: $pageRepository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
            'request_stack' => $this->createRequestStackWithSession(),
        ]));

        $controller->restore($this->createAdminContext($page), new Request(['token' => 'token']), $this->createStub(EntityManagerInterface::class));

        $this->assertSame('home', $page->getSlug());
        $this->assertNull($page->getArchivedSlug());
        $this->assertFalse($page->isDeleted());
    }

    // Someone else has taken the archived slug since - keeps the technical slug instead, still clears archivedSlug (no dangling reference to retry indefinitely)
    public function testRestoreKeepsTechnicalSlugWhenArchivedSlugIsTaken(): void
    {
        $page = new Page()->setTitle('Home')->setSlug('home-archived')->setArchivedSlug('home')->setIsDeleted(true);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findOneBy')->willReturn(new Page());

        $controller = $this->createController(pageRepository: $pageRepository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
            'request_stack' => $this->createRequestStackWithSession(),
        ]));

        $controller->restore($this->createAdminContext($page), new Request(['token' => 'token']), $this->createStub(EntityManagerInterface::class));

        $this->assertSame('home-archived', $page->getSlug());
        $this->assertNull($page->getArchivedSlug());
    }

    // A page trashed the regular way (never archived by a replacement swap) is untouched by this logic
    public function testRestoreLeavesSlugUntouchedWhenNeverArchived(): void
    {
        $page = new Page()->setTitle('Old Page')->setSlug('old-page')->setIsDeleted(true);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
            'request_stack' => $this->createRequestStackWithSession(),
        ]));

        $controller->restore($this->createAdminContext($page), new Request(['token' => 'token']), $this->createStub(EntityManagerInterface::class));

        $this->assertSame('old-page', $page->getSlug());
    }

    public function testQrcodeDeniesAccessBelowEditor(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(false),
        ]));

        $controller->qrcode($this->createAdminContext(new Page()));
    }

    public function testExportSqlDeniesAccessBelowSuperAdmin(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(false),
        ]));

        $controller->exportSql($this->createAdminContext(new Page()));
    }

    public function testExportJsonDeniesAccessBelowSuperAdmin(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(false),
        ]));

        $controller->exportJson($this->createAdminContext(new Page()));
    }

    public function testExportCsvDelegatesToTableExporterWhenGranted(): void
    {
        $connection = $this->createStub(Connection::class);
        $connection->method('fetchAllAssociative')->willReturn([['slug' => 'about']]);

        $tableExporter = $this->createMock(TableExporter::class);
        $tableExporter->expects($this->once())
            ->method('export')
            ->with(ExportFormat::Csv, 'site_page', [['slug' => 'about']])
            ->willReturn(new Response());

        $controller = $this->createController(connection: $connection, tableExporter: $tableExporter);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'request_stack' => $this->createRequestStackWithSession(),
        ]));

        $controller->exportCsv($this->createAdminContext(new Page()));
    }

    public function testExportSelectionDeniesAccessBelowSiteRoleAdmin(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(false),
        ]));

        $controller->exportSelection(
            $this->createAdminContext(new Page()),
            new BatchActionDto('exportSelection', [1], Page::class, 'token'),
        );
    }

    public function testExportSelectionThrowsBadRequestWhenEntityFqcnMismatches(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
        ]));

        $controller->exportSelection(
            $this->createAdminContext(new Page()),
            new BatchActionDto('exportSelection', [1], Redirect::class, 'token'),
        );
    }

    public function testExportSelectionRedirectsWhenCsrfTokenIsInvalid(): void
    {
        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->expects($this->never())->method('findBy');

        $controller = $this->createController(pageRepository: $pageRepository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(false),
        ]));

        $response = $controller->exportSelection(
            $this->createAdminContext(new Page()),
            new BatchActionDto('exportSelection', [1], Page::class, 'invalid'),
        );

        $this->assertSame('/management/pages', $response->getTargetUrl());
    }

    public function testExportSelectionExportsSelectedPagesWithTheirBlocks(): void
    {
        $block = new Block()->setKind('text')->setPosition(0)->setData(['content' => 'hello']);
        $page = new Page()->setTitle('About')->setSlug('about')->setIsPublished(true);
        $page->addBlock($block);

        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->expects($this->once())
            ->method('findBy')
            ->with(['id' => [1]])
            ->willReturn([$page]);

        $expectedItems = [[
            'title' => 'About',
            'slug' => 'about',
            'changeFrequency' => null,
            'priority' => null,
            'isPublished' => true,
            'isIndexable' => true,
            'summarySocialNetwork' => null,
            'options' => [],
            'ogImage' => null,
            'blocks' => [[
                'kind' => 'text',
                'position' => 0,
                'data' => ['content' => 'hello'],
                'animation' => null,
                'hidden' => false,
                'medias' => [],
                'slots' => [],
            ]],
        ]];

        $expectedResponse = new BinaryFileResponse(tempnam(sys_get_temp_dir(), 'export_test_'));
        $contentExporter = $this->createMock(ContentExporter::class);
        $contentExporter->expects($this->once())
            ->method('export')
            ->with('site_page', $expectedItems, [])
            ->willReturn($expectedResponse);

        $controller = $this->createController(pageRepository: $pageRepository, contentExporter: $contentExporter);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
        ]));

        $response = $controller->exportSelection(
            $this->createAdminContext(new Page()),
            new BatchActionDto('exportSelection', [1], Page::class, 'valid'),
        );

        $this->assertSame($expectedResponse, $response);
    }

    public function testExportSelectionRegistersMediaFilesAlongsideTheirMetadata(): void
    {
        $projectDir = sys_get_temp_dir() . '/page_export_test_' . bin2hex(random_bytes(4));
        mkdir($projectDir . '/public/uploads', 0777, true);
        $filename = 'uploads/photo.jpg';
        file_put_contents($projectDir . '/public/' . $filename, 'fake-image-bytes');

        $media = new Media()
            ->setFilename($filename)
            ->setRole('illustration')
            ->setAlt('A photo')
            ->setPosition(0);
        $block = new Block()->setKind('image')->setPosition(0)->setData([]);
        $block->addMedia($media);
        $page = new Page()->setTitle('About')->setSlug('about')->setIsPublished(true);
        $page->addBlock($block);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findBy')->willReturn([$page]);

        $contentExporter = $this->createMock(ContentExporter::class);
        $contentExporter->expects($this->once())
            ->method('export')
            ->with('site_page', $this->callback(function (array $items) use ($filename): bool {
                $medias = $items[0]['blocks'][0]['medias'];
                $this->assertCount(1, $medias);
                $this->assertSame('illustration', $medias[0]['role']);
                $this->assertSame('A photo', $medias[0]['alt']);
                $this->assertSame(basename($filename), $medias[0]['originalFilename']);
                $this->assertArrayHasKey('file', $medias[0]);
                $this->assertArrayNotHasKey('content', $medias[0]);

                return true;
            }), $this->callback(function (array $files) use ($projectDir, $filename): bool {
                $this->assertCount(1, $files);
                $diskPath = array_values($files)[0];
                $this->assertSame($projectDir . '/public/' . $filename, $diskPath);

                return true;
            }))
            ->willReturn(new BinaryFileResponse(tempnam(sys_get_temp_dir(), 'export_test_')));

        $controller = $this->createController(
            pageRepository: $pageRepository,
            contentExporter: $contentExporter,
            pageExportProvider: new PageExportProvider($pageRepository, new BlockDataExporter($projectDir)),
        );
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
        ]));

        $controller->exportSelection(
            $this->createAdminContext(new Page()),
            new BatchActionDto('exportSelection', [1], Page::class, 'valid'),
        );

        unlink($projectDir . '/public/' . $filename);
        rmdir($projectDir . '/public/uploads');
        rmdir($projectDir . '/public');
        rmdir($projectDir);
    }

    private function createCsrfTokenManager(bool $valid): CsrfTokenManagerInterface
    {
        $manager = $this->createStub(CsrfTokenManagerInterface::class);
        $manager->method('isTokenValid')->willReturn($valid);
        $manager->method('getToken')->willReturnCallback(static fn (string $tokenId): CsrfToken => new CsrfToken($tokenId, 'token'));

        return $manager;
    }
}
