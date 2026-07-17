<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 */

namespace c975L\SiteBundle\Tests\Controller;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Controller\PageController;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\SiteThemePresetProvider;
use c975L\SiteBundle\Service\PageServiceInterface;
use c975L\SiteBundle\Twig\CollectionItemContext;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Registry\CollectionSourceRegistry;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

class PageControllerTest extends TestCase
{
    private function createPageService(?Page $bySlug = null, array $forDisplayBySlug = []): PageServiceInterface
    {
        $service = $this->createStub(PageServiceInterface::class);
        $service->method('findOneBySlug')->willReturn($bySlug);
        $service->method('findForDisplay')->willReturnCallback(
            static fn (string $slug): ?Page => $forDisplayBySlug[$slug] ?? null
        );

        return $service;
    }

    private function createConfigService(): ConfigServiceInterface
    {
        $service = $this->createStub(ConfigServiceInterface::class);
        $service->method('get')->willReturn('ROLE_EDITOR');

        return $service;
    }

    // Wires a minimal container providing only the services AbstractController touches in this class:
    // 'twig' (render), 'router' (redirectToRoute/generateUrl) and 'security.authorization_checker'
    // (preview) - the same Environment stub is also passed as the constructor's own $twig (used by
    // resolveCollectionDetail()), matching real DI where both resolve to the same "twig" service
    private function createController(
        PageServiceInterface $pageService,
        ConfigServiceInterface $configService,
        bool $isGranted = true,
        ?SiteThemePresetProvider $themePresetProvider = null,
        ?CollectionSourceRegistry $collectionSourceRegistry = null,
        ?Environment $twig = null,
    ): PageController {
        if (null === $twig) {
            $twig = $this->createStub(Environment::class);
            $twig->method('render')->willReturnCallback(
                static fn (string $view, array $parameters = []): string => $view . ':' . ($parameters['page']->getTitle() ?? '')
            );
        }

        // Defaults to null (not a real SiteThemePresetProvider): that class implements ConfigBundle's
        // ThemePresetProviderInterface, which isn't in this bundle's own installed vendor snapshot yet
        // (same reason SiteThemePresetProviderTest already fails) - tests that don't cover preset
        // preview don't need to pay that cost, only the ones that pass a real provider explicitly do
        $controller = new PageController(
            $pageService,
            $configService,
            $collectionSourceRegistry ?? $this->createStub(CollectionSourceRegistry::class),
            $twig,
            new CollectionItemContext(),
            $themePresetProvider,
        );

        $router = $this->createStub(UrlGeneratorInterface::class);
        $router->method('generate')->willReturnCallback(
            static fn (string $name): string => '/' . $name
        );

        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn($isGranted);

        $container = new Container();
        $container->set('twig', $twig);
        $container->set('router', $router);
        $container->set('security.authorization_checker', $authorizationChecker);
        $controller->setContainer($container);

        return $controller;
    }

    public function testRedirectPagesRedirectsToHome(): void
    {
        $controller = $this->createController($this->createPageService(), $this->createConfigService());

        $response = $controller->redirectPages();

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame('/page_home', $response->getTargetUrl());
    }

    public function testRedirectIndexWrongMethodsRedirectsToHome(): void
    {
        $controller = $this->createController($this->createPageService(), $this->createConfigService());

        $this->assertSame('/page_home', $controller->redirectIndexWrongMethods()->getTargetUrl());
    }

    public function testRedirectPagesWrongMethodsRedirectsToHome(): void
    {
        $controller = $this->createController($this->createPageService(), $this->createConfigService());

        $this->assertSame('/page_home', $controller->redirectPagesWrongMethods()->getTargetUrl());
    }

    // The home page is rendered when the 'home' slug resolves to a Page
    public function testHomeRendersPageWhenFound(): void
    {
        $page = (new Page())->setTitle('Home')->setSlug('home');
        $controller = $this->createController($this->createPageService(bySlug: $page), $this->createConfigService());

        $response = $controller->home();

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('@c975LSite/pages/page.html.twig:Home', $response->getContent());
    }

    // No 'home' page exists yet (fresh install): a 404 is thrown rather than an error
    public function testHomeThrows404WhenPageNotFound(): void
    {
        $controller = $this->createController($this->createPageService(bySlug: null), $this->createConfigService());

        $this->expectException(NotFoundHttpException::class);
        $controller->home();
    }

    // The 'home' slug has one canonical URL: the site root, not /pages/home
    public function testDisplayRedirectsHomeSlugToSiteRoot(): void
    {
        $controller = $this->createController($this->createPageService(), $this->createConfigService());

        $response = $controller->display('home');

        $this->assertInstanceOf(RedirectResponse::class, $response);
        $this->assertSame(301, $response->getStatusCode());
        $this->assertSame('/page_home', $response->getTargetUrl());
    }

    // A trailing slash on the slug is trimmed before lookup/comparison
    public function testDisplayTrimsTrailingSlashBeforeRedirectingHomeSlug(): void
    {
        $controller = $this->createController($this->createPageService(), $this->createConfigService());

        $this->assertSame(301, $controller->display('home/')->getStatusCode());
    }

    // A deleted page yields a 410 Gone, not a plain 404 - lets clients/search engines know it's permanent
    public function testDisplayThrowsGoneWhenPageIsDeleted(): void
    {
        $page = (new Page())->setTitle('Gone')->setSlug('gone')->setIsPublished(true)->setIsDeleted(true);
        $controller = $this->createController(
            $this->createPageService(forDisplayBySlug: ['gone' => $page]),
            $this->createConfigService(),
        );

        $this->expectException(GoneHttpException::class);
        $controller->display('gone');
    }

    // An unpublished (draft) page is not publicly visible: 404, same as if it didn't exist
    public function testDisplayThrowsNotFoundWhenPageIsUnpublished(): void
    {
        $page = (new Page())->setTitle('Draft')->setSlug('draft')->setIsPublished(false);
        $controller = $this->createController(
            $this->createPageService(forDisplayBySlug: ['draft' => $page]),
            $this->createConfigService(),
        );

        $this->expectException(NotFoundHttpException::class);
        $controller->display('draft');
    }

    public function testDisplayThrowsNotFoundWhenPageDoesNotExist(): void
    {
        $controller = $this->createController($this->createPageService(), $this->createConfigService());

        $this->expectException(NotFoundHttpException::class);
        $controller->display('unknown');
    }

    public function testDisplayRendersPublishedNonDeletedPage(): void
    {
        $page = (new Page())->setTitle('About')->setSlug('about')->setIsPublished(true);
        $controller = $this->createController(
            $this->createPageService(forDisplayBySlug: ['about' => $page]),
            $this->createConfigService(),
        );

        $response = $controller->display('about');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('@c975LSite/pages/page.html.twig:About', $response->getContent());
    }

    // No exact Page for "catalog/item-1", but "catalog" exists with a "collection" block
    // (source + detailPage) - the item slug resolves against the source's own "detail" callable, then
    // the SEPARATE detail Page's own blocks render normally, with no dedicated Page/Block per item
    public function testDisplayResolvesCollectionDetailFromASeparateDetailPage(): void
    {
        $parent = (new Page())->setTitle('Catalog')->setSlug('catalog')->setIsPublished(true);
        $parent->addBlock((new Block())->setKind('collection')->setData([
            'source' => 'app.collection.demo',
            'detailPage' => 'catalog-detail',
        ]));

        $detailPage = (new Page())->setTitle('Detail template')->setSlug('catalog-detail')->setIsPublished(true);
        $detailPage->addBlock((new Block())->setKind('twig_content')->setData(['templatePath' => 'demo/detail.html.twig']));

        $collectionSourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $collectionSourceRegistry->method('detail')->willReturnCallback(
            static fn (string $source, string $slug): ?array => 'app.collection.demo' === $source && 'item-1' === $slug
                ? ['title' => 'Item One']
                : null
        );

        $capturedPageParameters = null;
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            function (string $view, array $parameters = []) use (&$capturedPageParameters): string {
                if ('@c975LSite/pages/page.html.twig' === $view) {
                    $capturedPageParameters = $parameters;

                    return 'page-shell';
                }

                // resolveCollectionDetail() rendering the detail Page's own blocks
                return $view . ':' . count($parameters['blocks']);
            }
        );

        $controller = $this->createController(
            $this->createPageService(forDisplayBySlug: [
                'catalog' => $parent,
                'catalog-detail' => $detailPage,
            ]),
            $this->createConfigService(),
            collectionSourceRegistry: $collectionSourceRegistry,
            twig: $twig,
        );

        $response = $controller->display('catalog/item-1');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('@c975LSite/pages/_blocks.html.twig:1', $capturedPageParameters['detailHtml']);
    }

    // A page can carry more than one "collection" block, each with its own source/detailPage - only
    // the one whose source actually resolves the item slug must win, not just the last one on the page
    // (the matching block is deliberately NOT last here, so a "last one wins" regression would 404)
    public function testDisplayResolvesTheCollectionBlockWhoseSourceMatchesWhenThePageHasSeveral(): void
    {
        $parent = (new Page())->setTitle('Catalog')->setSlug('catalog')->setIsPublished(true);
        $parent->addBlock((new Block())->setKind('collection')->setData([
            'source' => 'app.collection.team',
            'detailPage' => 'team-detail',
        ]));
        $parent->addBlock((new Block())->setKind('collection')->setData([
            'source' => 'app.collection.projects',
            'detailPage' => 'project-detail',
        ]));

        $projectDetailPage = (new Page())->setTitle('Project detail')->setSlug('project-detail')->setIsPublished(true);
        $teamDetailPage = (new Page())->setTitle('Team detail')->setSlug('team-detail')->setIsPublished(true);

        $collectionSourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $collectionSourceRegistry->method('detail')->willReturnCallback(
            static fn (string $source, string $slug): ?array => 'app.collection.team' === $source && 'member-1' === $slug
                ? ['title' => 'Member One']
                : null
        );

        $capturedPageParameters = null;
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            function (string $view, array $parameters = []) use (&$capturedPageParameters): string {
                if ('@c975LSite/pages/page.html.twig' === $view) {
                    $capturedPageParameters = $parameters;

                    return 'page-shell';
                }

                return $view . ':' . count($parameters['blocks']);
            }
        );

        $controller = $this->createController(
            $this->createPageService(forDisplayBySlug: [
                'catalog' => $parent,
                'project-detail' => $projectDetailPage,
                'team-detail' => $teamDetailPage,
            ]),
            $this->createConfigService(),
            collectionSourceRegistry: $collectionSourceRegistry,
            twig: $twig,
        );

        $response = $controller->display('catalog/member-1');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('Member One', $capturedPageParameters['detailTitle']);
    }

    // The item's data is exposed to the detail Page's own blocks via the "collectionItem" Twig global
    // (CollectionItemContext), not passed as this render's own $context
    public function testDisplaySetsTheCollectionItemContextBeforeRenderingTheDetailPage(): void
    {
        $parent = (new Page())->setTitle('Catalog')->setSlug('catalog')->setIsPublished(true);
        $parent->addBlock((new Block())->setKind('collection')->setData([
            'source' => 'app.collection.demo',
            'detailPage' => 'catalog-detail',
        ]));

        $detailPage = (new Page())->setTitle('Detail template')->setSlug('catalog-detail')->setIsPublished(true);

        $collectionSourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $collectionSourceRegistry->method('detail')->willReturn(['title' => 'Item One']);

        $collectionItemContext = new CollectionItemContext();
        $capturedItem = null;
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            function (string $view) use (&$capturedItem, $collectionItemContext): string {
                if ('@c975LSite/pages/_blocks.html.twig' === $view) {
                    $capturedItem = $collectionItemContext->get();
                }

                return $view;
            }
        );

        $controller = new PageController(
            $this->createPageService(forDisplayBySlug: [
                'catalog' => $parent,
                'catalog-detail' => $detailPage,
            ]),
            $this->createConfigService(),
            $collectionSourceRegistry,
            $twig,
            $collectionItemContext,
        );
        $router = $this->createStub(UrlGeneratorInterface::class);
        $container = new Container();
        $container->set('twig', $twig);
        $container->set('router', $router);
        $controller->setContainer($container);

        $controller->display('catalog/item-1');

        $this->assertSame(['title' => 'Item One'], $capturedItem);
    }

    // The item slug's title (from the source's own "detail" data) is forwarded as "detailTitle", so
    // that URL's <title> reflects the item, not the parent Page's own generic one
    public function testDisplayForwardsTheItemsOwnTitleAsDetailTitle(): void
    {
        $parent = (new Page())->setTitle('Catalog')->setSlug('catalog')->setIsPublished(true);
        $parent->addBlock((new Block())->setKind('collection')->setData([
            'source' => 'app.collection.demo',
            'detailPage' => 'catalog-detail',
        ]));
        $detailPage = (new Page())->setTitle('Detail template')->setSlug('catalog-detail')->setIsPublished(true);

        $collectionSourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $collectionSourceRegistry->method('detail')->willReturn(['title' => 'Item One']);

        $capturedPageParameters = null;
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            function (string $view, array $parameters = []) use (&$capturedPageParameters): string {
                if ('@c975LSite/pages/page.html.twig' === $view) {
                    $capturedPageParameters = $parameters;
                }

                return $view;
            }
        );

        $controller = $this->createController(
            $this->createPageService(forDisplayBySlug: [
                'catalog' => $parent,
                'catalog-detail' => $detailPage,
            ]),
            $this->createConfigService(),
            collectionSourceRegistry: $collectionSourceRegistry,
            twig: $twig,
        );

        $controller->display('catalog/item-1');

        $this->assertSame('Item One', $capturedPageParameters['detailTitle']);
    }

    // No "detailPage" set on the "collection" block: nothing tells resolveCollectionDetail() which Page
    // renders the detail view, so this falls through to a plain 404 - same as an unknown parent slug
    public function testDisplayThrowsNotFoundWhenTheCollectionBlockHasNoDetailPage(): void
    {
        $parent = (new Page())->setTitle('Catalog')->setSlug('catalog')->setIsPublished(true);
        $parent->addBlock((new Block())->setKind('collection')->setData(['source' => 'app.collection.demo']));

        $collectionSourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $collectionSourceRegistry->method('detail')->willReturn(['title' => 'Item One']);

        $controller = $this->createController(
            $this->createPageService(forDisplayBySlug: ['catalog' => $parent]),
            $this->createConfigService(),
            collectionSourceRegistry: $collectionSourceRegistry,
        );

        $this->expectException(NotFoundHttpException::class);
        $controller->display('catalog/item-1');
    }

    // "detailPage" is set but no Page exists with that slug (e.g. a typo, or it was deleted): falls
    // through to a 404 rather than an error
    public function testDisplayThrowsNotFoundWhenTheDetailPageDoesNotExist(): void
    {
        $parent = (new Page())->setTitle('Catalog')->setSlug('catalog')->setIsPublished(true);
        $parent->addBlock((new Block())->setKind('collection')->setData([
            'source' => 'app.collection.demo',
            'detailPage' => 'does-not-exist',
        ]));

        $collectionSourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $collectionSourceRegistry->method('detail')->willReturn(['title' => 'Item One']);

        $controller = $this->createController(
            $this->createPageService(forDisplayBySlug: ['catalog' => $parent]),
            $this->createConfigService(),
            collectionSourceRegistry: $collectionSourceRegistry,
        );

        $this->expectException(NotFoundHttpException::class);
        $controller->display('catalog/item-1');
    }

    // The source resolves no item for this slug: falls through to a 404
    public function testDisplayThrowsNotFoundWhenTheSourceResolvesNoItem(): void
    {
        $parent = (new Page())->setTitle('Catalog')->setSlug('catalog')->setIsPublished(true);
        $parent->addBlock((new Block())->setKind('collection')->setData([
            'source' => 'app.collection.demo',
            'detailPage' => 'catalog-detail',
        ]));
        $detailPage = (new Page())->setTitle('Detail template')->setSlug('catalog-detail')->setIsPublished(true);

        $collectionSourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $collectionSourceRegistry->method('detail')->willReturn(null);

        $controller = $this->createController(
            $this->createPageService(forDisplayBySlug: [
                'catalog' => $parent,
                'catalog-detail' => $detailPage,
            ]),
            $this->createConfigService(),
            collectionSourceRegistry: $collectionSourceRegistry,
        );

        $this->expectException(NotFoundHttpException::class);
        $controller->display('catalog/unknown');
    }

    // Preview is gated behind the configurable "site-role-editor" role - a visitor without it is denied
    public function testPreviewDeniesAccessWhenRoleNotGranted(): void
    {
        $controller = $this->createController($this->createPageService(), $this->createConfigService(), isGranted: false);

        $this->expectException(AccessDeniedException::class);
        $controller->preview('draft', new Request());
    }

    // An editor can preview a draft (unpublished) page - display() would 404 it, preview() must not
    public function testPreviewRendersUnpublishedPageAsPrivateResponse(): void
    {
        $page = (new Page())->setTitle('Draft')->setSlug('draft')->setIsPublished(false);
        $controller = $this->createController(
            $this->createPageService(forDisplayBySlug: ['draft' => $page]),
            $this->createConfigService(),
        );

        $response = $controller->preview('draft', new Request());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    // A deleted page still can't be previewed, even by an editor
    public function testPreviewThrowsNotFoundWhenPageIsDeleted(): void
    {
        $page = (new Page())->setTitle('Gone')->setSlug('gone')->setIsPublished(true)->setIsDeleted(true);
        $controller = $this->createController(
            $this->createPageService(forDisplayBySlug: ['gone' => $page]),
            $this->createConfigService(),
        );

        $this->expectException(NotFoundHttpException::class);
        $controller->preview('gone', new Request());
    }

    public function testPreviewThrowsNotFoundWhenPageDoesNotExist(): void
    {
        $controller = $this->createController($this->createPageService(), $this->createConfigService());

        $this->expectException(NotFoundHttpException::class);
        $controller->preview('unknown', new Request());
    }

    // An editor can preview an unpublished parent Page's own collection detail views too, before
    // publishing anything - same resolveCollectionDetail() fallback as display()
    public function testPreviewResolvesCollectionDetailForAnUnpublishedParentPage(): void
    {
        $parent = (new Page())->setTitle('Catalog')->setSlug('catalog')->setIsPublished(false);
        $parent->addBlock((new Block())->setKind('collection')->setData([
            'source' => 'app.collection.demo',
            'detailPage' => 'catalog-detail',
        ]));
        $detailPage = (new Page())->setTitle('Detail template')->setSlug('catalog-detail')->setIsPublished(true);

        $collectionSourceRegistry = $this->createStub(CollectionSourceRegistry::class);
        $collectionSourceRegistry->method('detail')->willReturn(['title' => 'Item One']);

        $capturedPageParameters = null;
        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            function (string $view, array $parameters = []) use (&$capturedPageParameters): string {
                if ('@c975LSite/pages/page.html.twig' === $view) {
                    $capturedPageParameters = $parameters;

                    return 'page-shell';
                }

                return $view . ':' . count($parameters['blocks']);
            }
        );

        $controller = $this->createController(
            $this->createPageService(forDisplayBySlug: [
                'catalog' => $parent,
                'catalog-detail' => $detailPage,
            ]),
            $this->createConfigService(),
            collectionSourceRegistry: $collectionSourceRegistry,
            twig: $twig,
        );

        $response = $controller->preview('catalog/item-1', new Request());

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('@c975LSite/pages/_blocks.html.twig:0', $capturedPageParameters['detailHtml']);
    }

    // Without a wired SiteThemePresetProvider (optional dependency), ?preset is simply ignored -
    // graceful degradation rather than a hard failure
    public function testPreviewResolvesToNullPresetWhenNoneRequested(): void
    {
        $page = (new Page())->setTitle('Draft')->setSlug('draft')->setIsPublished(false);
        $controller = $this->createController(
            $this->createPageService(forDisplayBySlug: ['draft' => $page]),
            $this->createConfigService(),
        );

        $twig = $this->createStub(Environment::class);
        $capturedParameters = null;
        $twig->method('render')->willReturnCallback(
            function (string $view, array $parameters = []) use (&$capturedParameters): string {
                $capturedParameters = $parameters;

                return $view;
            }
        );
        $authorizationChecker = $this->createStub(AuthorizationCheckerInterface::class);
        $authorizationChecker->method('isGranted')->willReturn(true);
        $container = new Container();
        $container->set('twig', $twig);
        $container->set('router', $this->createStub(UrlGeneratorInterface::class));
        $container->set('security.authorization_checker', $authorizationChecker);
        $controller->setContainer($container);

        $controller->preview('draft', new Request(['preset' => 'anything']));

        $this->assertNull($capturedParameters['previewPreset']);
    }
}
