<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Controller;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Controller\PageController;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Service\PageServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Exception\GoneHttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Twig\Environment;

// Lives under src/Tests (not a sibling tests/ dir) so it stays autoloadable by consuming apps,
// whose attribute route loader recursively reflects every class under the bundle root
class PageControllerTest extends TestCase
{
    private function createPageService(?Page $bySlug = null, ?Page $forDisplay = null): PageServiceInterface
    {
        $service = $this->createStub(PageServiceInterface::class);
        $service->method('findOneBySlug')->willReturn($bySlug);
        $service->method('findForDisplay')->willReturn($forDisplay);

        return $service;
    }

    private function createConfigService(bool $isGrantedRole = true): ConfigServiceInterface
    {
        $service = $this->createStub(ConfigServiceInterface::class);
        $service->method('get')->willReturn('ROLE_EDITOR');

        return $service;
    }

    // Wires a minimal container providing only the services AbstractController touches in this class:
    // 'twig' (render), 'router' (redirectToRoute/generateUrl) and 'security.authorization_checker' (preview)
    private function createController(
        PageServiceInterface $pageService,
        ConfigServiceInterface $configService,
        bool $isGranted = true,
    ): PageController {
        $controller = new PageController($pageService, $configService);

        $twig = $this->createStub(Environment::class);
        $twig->method('render')->willReturnCallback(
            static fn (string $view, array $parameters = []): string => $view . ':' . ($parameters['page']->getTitle() ?? '')
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
        $controller = $this->createController($this->createPageService(forDisplay: $page), $this->createConfigService());

        $this->expectException(GoneHttpException::class);
        $controller->display('gone');
    }

    // An unpublished (draft) page is not publicly visible: 404, same as if it didn't exist
    public function testDisplayThrowsNotFoundWhenPageIsUnpublished(): void
    {
        $page = (new Page())->setTitle('Draft')->setSlug('draft')->setIsPublished(false);
        $controller = $this->createController($this->createPageService(forDisplay: $page), $this->createConfigService());

        $this->expectException(NotFoundHttpException::class);
        $controller->display('draft');
    }

    public function testDisplayThrowsNotFoundWhenPageDoesNotExist(): void
    {
        $controller = $this->createController($this->createPageService(forDisplay: null), $this->createConfigService());

        $this->expectException(NotFoundHttpException::class);
        $controller->display('unknown');
    }

    public function testDisplayRendersPublishedNonDeletedPage(): void
    {
        $page = (new Page())->setTitle('About')->setSlug('about')->setIsPublished(true);
        $controller = $this->createController($this->createPageService(forDisplay: $page), $this->createConfigService());

        $response = $controller->display('about');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertSame('@c975LSite/pages/page.html.twig:About', $response->getContent());
    }

    // Preview is gated behind the configurable "site-role-editor" role - a visitor without it is denied
    public function testPreviewDeniesAccessWhenRoleNotGranted(): void
    {
        $controller = $this->createController($this->createPageService(), $this->createConfigService(), isGranted: false);

        $this->expectException(AccessDeniedException::class);
        $controller->preview('draft');
    }

    // An editor can preview a draft (unpublished) page - display() would 404 it, preview() must not
    public function testPreviewRendersUnpublishedPageAsPrivateResponse(): void
    {
        $page = (new Page())->setTitle('Draft')->setSlug('draft')->setIsPublished(false);
        $controller = $this->createController($this->createPageService(forDisplay: $page), $this->createConfigService());

        $response = $controller->preview('draft');

        $this->assertSame(200, $response->getStatusCode());
        $this->assertTrue($response->headers->hasCacheControlDirective('private'));
    }

    // A deleted page still can't be previewed, even by an editor
    public function testPreviewThrowsNotFoundWhenPageIsDeleted(): void
    {
        $page = (new Page())->setTitle('Gone')->setSlug('gone')->setIsPublished(true)->setIsDeleted(true);
        $controller = $this->createController($this->createPageService(forDisplay: $page), $this->createConfigService());

        $this->expectException(NotFoundHttpException::class);
        $controller->preview('gone');
    }

    public function testPreviewThrowsNotFoundWhenPageDoesNotExist(): void
    {
        $controller = $this->createController($this->createPageService(forDisplay: null), $this->createConfigService());

        $this->expectException(NotFoundHttpException::class);
        $controller->preview('unknown');
    }
}
