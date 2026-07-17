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
use c975L\ConfigBundle\Service\Export\ExportFormat;
use c975L\ConfigBundle\Service\Export\TableExporter;
use c975L\SiteBundle\Controller\Management\PageCrudController;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Entity\Redirect;
use c975L\SiteBundle\Management\TemplateApplier;
use c975L\SiteBundle\Management\TemplateRegistry;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Repository\RedirectRepository;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use Doctrine\DBAL\Connection;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Mapping\ClassMetadata;
use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\UnitOfWork;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Orm\EntityRepositoryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Registry\AdminControllerRegistryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Router\AdminRouteGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\String\Slugger\SluggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// App\Entity\User (the type Page::setUser() actually requires) belongs to the consuming application,
// not to this standalone bundle checkout - so Security::getUser() is always stubbed to null here,
// covering the "nobody logged in" branch only, same limitation as UiBundle's BlockUserListenerTest
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

        $requestStack = new RequestStack();
        $requestStack->push($request);

        return $requestStack;
    }

    // AdminUrlGenerator is final - can't be mocked, so it's built for real with stubbed interface
    // collaborators, matching how the framework itself wires it
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

    // AdminContextProvider is final too, but trivial enough (just reads a request attribute) to
    // build for real instead - avoids needing to mock it
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
        ?TemplateRegistry $templateRegistry = null,
        ?TemplateApplier $templateApplier = null,
    ): PageCrudController {
        $translatorStub = $translator ?? $this->createStub(TranslatorInterface::class);
        if (null === $translator) {
            $translatorStub->method('trans')->willReturnArgument(0);
        }

        return new PageCrudController(
            $security ?? $this->createStub(Security::class),
            $configService ?? $this->createConfigService(),
            $adminUrlGenerator ?? $this->createAdminUrlGenerator(),
            $translatorStub,
            $redirectRepository ?? $this->createStub(RedirectRepository::class),
            $pageRepository ?? $this->createStub(PageRepository::class),
            $adminContextProvider ?? $this->createAdminContextProvider(),
            $requestStack ?? new RequestStack(),
            $slugger ?? new AsciiSlugger(),
            $connection ?? $this->createStub(Connection::class),
            $tableExporter ?? $this->createStub(TableExporter::class),
            $templateRegistry ?? new TemplateRegistry([]),
            $templateApplier ?? new TemplateApplier(),
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
        return (new \ReflectionMethod($controller, $method))->invoke($controller, ...$args);
    }

    private function createAdminContext(Page $page): AdminContext
    {
        $entityDto = new EntityDto(Page::class, new ClassMetadata(Page::class), null, $page);

        return AdminContext::forTesting(crudContext: CrudContext::forTesting(entityDto: $entityDto));
    }

    // --- persistEntity ------------------------------------------------------------------------------

    public function testPersistEntitySetsDatesSlugifiesAndDelegatesToParent(): void
    {
        $controller = $this->createController();
        $page = (new Page())->setTitle('New Page')->setSlug('Néw Pägé!');

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
        $page = (new Page())->setTitle('New Page')->setSlug('new-page');

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
        $page = (new Page())->setTitle('Same Title')->setSlug('same-title');

        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn(['slug' => 'same-title', 'title' => 'Same Title']);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->method('getUnitOfWork')->willReturn($unitOfWork);
        $manager->expects($this->once())->method('persist')->with($page);
        $manager->expects($this->once())->method('flush');

        $this->createController()->updateEntity($manager, $page);

        $this->assertNotNull($page->getModification());
    }

    // Resyncs the slug from the new title, mirroring SlugField's own JS behavior server-side
    // (see the "titleConfirm" Stimulus controller referenced in configureFields)
    public function testUpdateEntityResyncsSlugWhenTitleChanges(): void
    {
        $page = (new Page())->setTitle('Renamed Page')->setSlug('old-title');

        $manager = $this->createManagerWithOriginalData(['slug' => 'old-title', 'title' => 'Old Title']);

        $this->createController()->updateEntity($manager, $page);

        $this->assertSame('renamed-page', $page->getSlug());
    }

    // The home page's slug is fixed regardless of title changes (see isHomePage in configureFields)
    public function testUpdateEntityDoesNotResyncSlugForHomePage(): void
    {
        $page = (new Page())->setTitle('Renamed Home')->setSlug('home');

        $manager = $this->createManagerWithOriginalData(['slug' => 'home', 'title' => 'Old Home Title']);

        $this->createController()->updateEntity($manager, $page);

        $this->assertSame('home', $page->getSlug());
    }

    public function testUpdateEntityCreatesARedirectWhenSlugChanges(): void
    {
        $page = (new Page())->setTitle('Same Title')->setSlug('new-slug');

        $manager = $this->createManagerWithOriginalData(['slug' => 'old-slug', 'title' => 'Same Title']);
        // parent::updateEntity() also persists the Page itself - capture every persisted entity
        // rather than asserting a single call, since a Redirect is persisted in addition to it
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

    public function testUpdateEntityDoesNotCreateARedirectWhenSlugIsUnchanged(): void
    {
        $page = (new Page())->setTitle('Same Title')->setSlug('same-slug');

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
        $page = (new Page())->setTitle('Same Title')->setSlug('same-title');
        $manager = $this->createManagerWithOriginalData(['slug' => 'same-title', 'title' => 'Same Title']);

        $this->createController()->updateEntity($manager, $page);

        $this->assertNull($page->getUser());
    }

    // --- deleteEntity (move to trash) -----------------------------------------------------------------

    public function testDeleteEntityMovesPageToTrashWithoutRemovingIt(): void
    {
        $page = (new Page())->setTitle('Old Page')->setSlug('old-page')->setIsPublished(true);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->never())->method('remove');
        $manager->expects($this->once())->method('flush');

        $this->createController()->deleteEntity($manager, $page);

        $this->assertTrue($page->isDeleted());
        $this->assertFalse($page->isPublished());
        $this->assertNotNull($page->getModification());
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
        $source = (new Page())
            ->setTitle('Original')
            ->setSlug('original')
            ->setSummarySocialNetwork('summary')
            ->setPriority(5)
            ->setChangeFrequency('weekly')
            ->setIsPublished(true);

        $block = (new Block())->setKind('article')->setPosition(0)->setData(['title' => 'Hello'])->setAnimation('fade');
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
        $source = (new Page())->setTitle('Original')->setSlug('original')->setIsPublished(true);

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
        $source = (new Page())->setTitle('Original')->setSlug('original');
        $media = (new Media())->setAlt('alt text')->setLabel('caption');
        $block = (new Block())->setKind('article')->setData(['title' => 'x']);
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

    // --- applyTemplate -------------------------------------------------------------------------------

    public function testApplyTemplateDeniesAccessBelowEditor(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(false),
        ]));

        $controller->applyTemplate(
            $this->createAdminContext(new Page()),
            new Request(),
            $this->createStub(EntityManagerInterface::class)
        );
    }

    // Never mutates the live/source page - builds an unpublished copy carrying the template's blocks
    // and marked as replacing the source, then redirects to editing the copy, not the source
    public function testApplyTemplateCreatesAnUnpublishedCopyMarkedAsReplacingTheSource(): void
    {
        $source = (new Page())->setTitle('Home')->setSlug('home');
        (new \ReflectionProperty(Page::class, 'id'))->setValue($source, 42);

        $templateRegistry = $this->createMock(TemplateRegistry::class);
        $templateRegistry->expects($this->once())->method('get')->with('agency-home')->willReturn([
            'label' => 'label.test',
            'blocks' => [
                ['kind' => 'hero', 'data' => ['title' => 'Hello']],
            ],
        ]);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findOneBy')->willReturn(null);

        $capturedCopy = null;
        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->once())->method('persist')->willReturnCallback(
            function (object $entity) use (&$capturedCopy): void {
                $capturedCopy = $entity;
            }
        );
        $manager->expects($this->once())->method('flush');

        $controller = $this->createController(pageRepository: $pageRepository, templateRegistry: $templateRegistry);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'request_stack' => $this->createRequestStackWithSession(),
        ]));

        $response = $controller->applyTemplate(
            $this->createAdminContext($source),
            new Request(['template' => 'agency-home']),
            $manager
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertCount(0, $source->getBlocks());
        $this->assertInstanceOf(Page::class, $capturedCopy);
        $this->assertCount(1, $capturedCopy->getBlocks());
        $this->assertSame('hero', $capturedCopy->getBlocks()->first()->getKind());
        $this->assertFalse($capturedCopy->isPublished());
        $this->assertSame(42, $capturedCopy->getReplaces());
    }

    // An unknown ?template=<slug> is a no-op: no copy created, nothing persisted/flushed, redirects
    // back to the source page itself (there is no copy to redirect to)
    public function testApplyTemplateRedirectsToSourceWhenTemplateUnknown(): void
    {
        $page = (new Page())->setTitle('Home')->setSlug('home');

        $templateRegistry = $this->createStub(TemplateRegistry::class);
        $templateRegistry->method('get')->willReturn(null);

        $manager = $this->createMock(EntityManagerInterface::class);
        $manager->expects($this->never())->method('persist');
        $manager->expects($this->never())->method('flush');

        $controller = $this->createController(templateRegistry: $templateRegistry);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'request_stack' => $this->createRequestStackWithSession(),
        ]));

        $response = $controller->applyTemplate(
            $this->createAdminContext($page),
            new Request(['template' => 'unknown']),
            $manager
        );

        $this->assertSame(302, $response->getStatusCode());
        $this->assertCount(0, $page->getBlocks());
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

    // The original is looked up by id (not slug, since a concurrent draft's own publishAsReplacement()
    // may have since changed it), its slug is archived first (own flush) so the unique constraint on
    // slug is never violated, then the copy takes it over, gets published, and "replaces" is cleared
    public function testPublishAsReplacementSwapsSlugsPublishesCopyAndTrashesOriginal(): void
    {
        $original = (new Page())->setTitle('Home')->setSlug('home')->setIsPublished(true);
        (new \ReflectionProperty(Page::class, 'id'))->setValue($original, 7);
        $copy = (new Page())->setTitle('Home (copy)')->setSlug('home-copy')->setReplaces(7);

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

    // The original may already be gone (deleted/renamed since the copy was created) - aborts safely,
    // flashes an error, never touches the copy
    public function testPublishAsReplacementFlashesErrorWhenOriginalNotFound(): void
    {
        $copy = (new Page())->setTitle('Draft')->setSlug('draft')->setReplaces(999);

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

    // Two drafts created (via applyTemplate) from the same original before either is published: the
    // first publish archives the original (non-null archivedSlug, mangled slug). The second draft's own
    // publishAsReplacement() must not take over that mangled slug - it's treated the same as "original
    // not found" instead of silently publishing under a garbage URL
    public function testPublishAsReplacementFlashesErrorWhenOriginalAlreadyArchivedByAnotherDraft(): void
    {
        $original = (new Page())->setTitle('Home')->setSlug('home-archived')->setArchivedSlug('home');
        (new \ReflectionProperty(Page::class, 'id'))->setValue($original, 7);
        $copy = (new Page())->setTitle('Home (copy 2)')->setSlug('home-copy-2')->setReplaces(7);

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

    public function testUniqueSlugReturnsTheBaseSlugWhenAvailable(): void
    {
        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findOneBy')->willReturn(null);

        $controller = $this->createController(pageRepository: $pageRepository);

        $this->assertSame('my-page', $this->invokePrivate($controller, 'uniqueSlug', ['My Page']));
    }

    // Appends -2, -3... on collision, matching the class comment's own documented behavior
    public function testUniqueSlugAppendsASuffixOnCollision(): void
    {
        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findOneBy')->willReturnMap([
            [['slug' => 'my-page'], null, (new Page())],
            [['slug' => 'my-page-2'], null, null],
        ]);

        $controller = $this->createController(pageRepository: $pageRepository);

        $this->assertSame('my-page-2', $this->invokePrivate($controller, 'uniqueSlug', ['My Page']));
    }

    // --- slugifyPage (private) -------------------------------------------------------------------------

    public function testSlugifyPageNormalizesAccentsSpacesAndCase(): void
    {
        $controller = $this->createController();
        $page = (new Page())->setTitle('x')->setSlug('Héllo Wörld!');

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

        $page = (new Page())->setTitle('x')->setSlug('draft-page')->setIsPublished(false);

        $this->assertSame('page_preview:draft-page', $this->invokePrivate($controller, 'pagePath', [$page]));
    }

    public function testPagePathPointsToHomeRouteForTheHomeSlug(): void
    {
        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(static fn (string $route) => $route);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer(['router' => $router]));

        $page = (new Page())->setTitle('x')->setSlug('home')->setIsPublished(true);

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

        $page = (new Page())->setTitle('x')->setSlug('about')->setIsPublished(true);

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

        $page = (new Page())->setTitle('x')->setSlug('about')->setIsPublished(true);

        $this->assertSame('https://example.com/about', $this->invokePrivate($controller, 'buildPageUrl', [$page]));
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

    public function testConfigureActionsBuildsWithoutError(): void
    {
        // A real EasyAdmin runtime pre-populates default actions (EDIT, DELETE...) before calling
        // configureActions() - reorder()/update() below assume EDIT already exists on PAGE_INDEX
        $actions = $this->createController()->configureActions(
            Actions::new()
                ->add(Crud::PAGE_INDEX, Action::EDIT)
                ->add(Crud::PAGE_INDEX, Action::DELETE)
                ->add(Crud::PAGE_DETAIL, Action::EDIT)
                ->add(Crud::PAGE_DETAIL, Action::DELETE)
        );

        $this->assertInstanceOf(Actions::class, $actions);
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

    // The titleConfirm Stimulus controller (assets/js/title-confirm.js) reuses EasyAdmin's confirmation
    // modal, which isn't rendered on the "new" crud page (only edit/index/detail) - and there's no
    // existing slug to preserve yet anyway, so the field must stay plain there
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

        $this->assertSame('titleConfirm', $title->getAsDto()->getFormTypeOptions()['attr']['data-controller'] ?? null);
    }

    public function testCreateIndexQueryBuilderFiltersOutDeletedPagesByDefault(): void
    {
        $requestStack = new RequestStack();
        $requestStack->push(new Request());

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

    // --- access-denied smoke tests ---------------------------------------------------------------------

    public function testDeletePermanentlyDeniesAccessBelowAdmin(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(false),
        ]));

        $controller->deletePermanently($this->createAdminContext(new Page()), $this->createStub(EntityManagerInterface::class));
    }

    public function testRestoreDeniesAccessBelowAdmin(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(false),
        ]));

        $controller->restore($this->createAdminContext(new Page()), $this->createStub(EntityManagerInterface::class));
    }

    // A page archived by publishAsReplacement() reclaims its real slug on restore if nothing else has
    // taken it since, and archivedSlug is cleared
    public function testRestoreReclaimsArchivedSlugWhenFree(): void
    {
        $page = (new Page())->setTitle('Home')->setSlug('home-archived')->setArchivedSlug('home')->setIsDeleted(true);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findOneBy')->willReturn(null);

        $controller = $this->createController(pageRepository: $pageRepository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'request_stack' => $this->createRequestStackWithSession(),
        ]));

        $controller->restore($this->createAdminContext($page), $this->createStub(EntityManagerInterface::class));

        $this->assertSame('home', $page->getSlug());
        $this->assertNull($page->getArchivedSlug());
        $this->assertFalse($page->isDeleted());
    }

    // Someone else has taken the archived slug since - keeps the technical slug instead, still clears
    // archivedSlug (no dangling reference to retry indefinitely)
    public function testRestoreKeepsTechnicalSlugWhenArchivedSlugIsTaken(): void
    {
        $page = (new Page())->setTitle('Home')->setSlug('home-archived')->setArchivedSlug('home')->setIsDeleted(true);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findOneBy')->willReturn(new Page());

        $controller = $this->createController(pageRepository: $pageRepository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'request_stack' => $this->createRequestStackWithSession(),
        ]));

        $controller->restore($this->createAdminContext($page), $this->createStub(EntityManagerInterface::class));

        $this->assertSame('home-archived', $page->getSlug());
        $this->assertNull($page->getArchivedSlug());
    }

    // A page trashed the regular way (never archived by a replacement swap) is untouched by this logic
    public function testRestoreLeavesSlugUntouchedWhenNeverArchived(): void
    {
        $page = (new Page())->setTitle('Old Page')->setSlug('old-page')->setIsDeleted(true);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'request_stack' => $this->createRequestStackWithSession(),
        ]));

        $controller->restore($this->createAdminContext($page), $this->createStub(EntityManagerInterface::class));

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
}
