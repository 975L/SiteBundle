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
use c975L\SiteBundle\Controller\Management\FontBulkImportController;
use c975L\SiteBundle\Entity\Font;
use c975L\SiteBundle\Service\FontFilenameParser;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Registry\AdminControllerRegistryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Router\AdminRouteGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

class FontBulkImportControllerTest extends TestCase
{
    use ControllerContainerTestTrait;

    private function createAdminUrlGenerator(string $generatedUrl = '/management/fonts'): AdminUrlGenerator
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
        ?EntityManagerInterface $entityManager = null,
        ?AdminUrlGenerator $adminUrlGenerator = null,
    ): FontBulkImportController {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('site-role-editor');

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        return new FontBulkImportController(
            $configService,
            new FontFilenameParser(),
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $adminUrlGenerator ?? $this->createAdminUrlGenerator(),
            $translator,
        );
    }

    private function createFontFile(string $originalName, string $content = 'fake-font-bytes'): UploadedFile
    {
        $path = tempnam(sys_get_temp_dir(), 'font_bulk_import_test_');
        file_put_contents($path, $content);

        return new UploadedFile($path, $originalName, null, null, true);
    }

    public function testIndexDeniesAccessBelowSiteRoleEditor(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(false),
        ]));

        $controller->index(new Request());
    }

    public function testIndexRendersTheUploadFormOnGet(): void
    {
        $twig = $this->createMock(Environment::class);
        $twig->expects($this->once())
            ->method('render')
            ->with('@c975LSite/management/font_bulk_import.html.twig', [])
            ->willReturn('<form></form>');

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'twig' => $twig,
        ]));

        $response = $controller->index(new Request());

        $this->assertSame('<form></form>', $response->getContent());
    }

    public function testIndexRedirectsWhenCsrfTokenIsInvalid(): void
    {
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');

        [$requestStack, $session] = $this->createRequestStackWithSession();

        $controller = $this->createController(entityManager: $entityManager);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(false),
            'router' => $this->createRouter(),
            'request_stack' => $requestStack,
        ]));

        $response = $controller->index(Request::create('/font-bulk-import', 'POST', ['_token' => 'invalid']));

        $this->assertSame('/management', $response->getTargetUrl());
        $this->assertSame(['flash.font_bulk_import_invalid_token'], $session->getFlashBag()->get('danger'));
    }

    public function testIndexFlashesDangerWhenNoFileIsUploaded(): void
    {
        [$requestStack, $session] = $this->createRequestStackWithSession();

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
            'router' => $this->createRouter(),
            'request_stack' => $requestStack,
        ]));

        $controller->index(Request::create('/font-bulk-import', 'POST', ['_token' => 'valid']));

        $this->assertSame(['flash.font_bulk_import_no_files'], $session->getFlashBag()->get('danger'));
    }

    // The invalid extension is skipped with a warning, the two valid files persisted
    public function testIndexCreatesOneFontPerValidFileAndSkipsInvalidOnes(): void
    {
        [$requestStack, $session] = $this->createRequestStackWithSession();

        $bold = $this->createFontFile('Roboto-Bold.ttf');
        $italic = $this->createFontFile('OpenSans-Italic.woff2');
        $invalid = $this->createFontFile('not-a-font.txt');

        $persisted = [];
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->exactly(2))
            ->method('persist')
            ->with($this->callback(function (Font $font) use (&$persisted): bool {
                $persisted[] = $font;

                return true;
            }));
        $entityManager->expects($this->once())->method('flush');

        $adminUrlGenerator = $this->createAdminUrlGenerator('/management/fonts');

        $controller = $this->createController(entityManager: $entityManager, adminUrlGenerator: $adminUrlGenerator);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
            'router' => $this->createRouter(),
            'request_stack' => $requestStack,
        ]));

        $request = Request::create('/font-bulk-import', 'POST', ['_token' => 'valid']);
        $request->files->set('files', [$bold, $italic, $invalid]);

        $response = $controller->index($request);

        $this->assertSame('/management/fonts', $response->getTargetUrl());
        $this->assertSame(['flash.font_bulk_import_success'], $session->getFlashBag()->get('success'));
        $this->assertSame(['flash.font_bulk_import_skipped'], $session->getFlashBag()->get('warning'));

        $this->assertCount(2, $persisted);
        $this->assertSame('Roboto', $persisted[0]->getName());
        $this->assertSame(700, $persisted[0]->getWeight());
        $this->assertSame('normal', $persisted[0]->getStyle());
        $this->assertSame('Open Sans', $persisted[1]->getName());
        $this->assertSame(400, $persisted[1]->getWeight());
        $this->assertSame('italic', $persisted[1]->getStyle());

        foreach ([$bold, $italic, $invalid] as $file) {
            @unlink($file->getPathname());
        }
    }

    // Nothing to persist/flush and nowhere new to redirect - the admin stays on the import form to fix their selection
    public function testIndexRedirectsBackToFormWhenEveryFileIsInvalid(): void
    {
        [$requestStack, $session] = $this->createRequestStackWithSession();
        $invalid = $this->createFontFile('not-a-font.txt');

        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->expects($this->never())->method('persist');
        $entityManager->expects($this->never())->method('flush');

        $controller = $this->createController(entityManager: $entityManager);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
            'router' => $this->createRouter('/management/site/font-bulk-import'),
            'request_stack' => $requestStack,
        ]));

        $request = Request::create('/font-bulk-import', 'POST', ['_token' => 'valid']);
        $request->files->set('files', [$invalid]);

        $response = $controller->index($request);

        $this->assertSame('/management/site/font-bulk-import', $response->getTargetUrl());
        $this->assertSame(['flash.font_bulk_import_skipped'], $session->getFlashBag()->get('warning'));
        $this->assertSame([], $session->getFlashBag()->get('success'));

        @unlink($invalid->getPathname());
    }
}
