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
use c975L\ConfigBundle\Service\Export\ContentExporter;
use c975L\SiteBundle\Controller\Management\FontCrudController;
use c975L\SiteBundle\Entity\Font;
use c975L\SiteBundle\Repository\FontRepository;
use Doctrine\ORM\Mapping\ClassMetadata;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\CrudContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Registry\AdminControllerRegistryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Router\AdminRouteGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Dto\BatchActionDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\DependencyInjection\Container;
use Symfony\Component\DependencyInjection\ParameterBag\ContainerBagInterface;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authorization\AuthorizationCheckerInterface;
use Symfony\Component\Security\Core\Exception\AccessDeniedException;
use Symfony\Component\Security\Csrf\CsrfToken;
use Symfony\Component\Security\Csrf\CsrfTokenManagerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class FontCrudControllerTest extends TestCase
{
    private function createContainer(array $services): Container
    {
        $container = new Container();
        foreach ($services as $id => $service) {
            $container->set($id, $service);
        }

        return $container;
    }

    private function createCsrfTokenManager(bool $valid): CsrfTokenManagerInterface
    {
        $manager = $this->createStub(CsrfTokenManagerInterface::class);
        $manager->method('isTokenValid')->willReturnCallback(static fn (CsrfToken $token) => $valid);

        return $manager;
    }

    private function createAuthorizationChecker(bool $granted): AuthorizationCheckerInterface
    {
        $checker = $this->createStub(AuthorizationCheckerInterface::class);
        $checker->method('isGranted')->willReturn($granted);

        return $checker;
    }

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

    private function createAdminContext(): AdminContext
    {
        $entityDto = new EntityDto(Font::class, new ClassMetadata(Font::class), null, new Font());

        return AdminContext::forTesting(crudContext: CrudContext::forTesting(entityDto: $entityDto));
    }

    private function createController(
        ?FontRepository $fontRepository = null,
        ?ContentExporter $contentExporter = null,
    ): FontCrudController {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('site-role-editor');

        return new FontCrudController(
            $configService,
            $translator,
            $fontRepository ?? $this->createStub(FontRepository::class),
            $this->createAdminUrlGenerator(),
            $contentExporter ?? $this->createStub(ContentExporter::class),
        );
    }

    public function testExportSelectionDeniesAccessBelowSiteRoleAdmin(): void
    {
        $this->expectException(AccessDeniedException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(false),
        ]));

        $controller->exportSelection($this->createAdminContext(), new BatchActionDto('exportSelection', [1], Font::class, 'token'));
    }

    public function testExportSelectionThrowsBadRequestWhenEntityFqcnMismatches(): void
    {
        $this->expectException(BadRequestHttpException::class);

        $controller = $this->createController();
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
        ]));

        $controller->exportSelection($this->createAdminContext(), new BatchActionDto('exportSelection', [1], \stdClass::class, 'token'));
    }

    public function testExportSelectionRedirectsWhenCsrfTokenIsInvalid(): void
    {
        $fontRepository = $this->createMock(FontRepository::class);
        $fontRepository->expects($this->never())->method('findBy');

        $controller = $this->createController(fontRepository: $fontRepository);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(false),
        ]));

        $response = $controller->exportSelection($this->createAdminContext(), new BatchActionDto('exportSelection', [1], Font::class, 'invalid'));

        $this->assertSame('/management/fonts', $response->getTargetUrl());
    }

    public function testExportSelectionEncodesFileContentAlongsideItsMetadata(): void
    {
        $projectDir = sys_get_temp_dir() . '/font_export_test_' . bin2hex(random_bytes(4));
        mkdir($projectDir . '/public/uploads', 0777, true);
        $filename = 'uploads/roboto-bold.woff2';
        file_put_contents($projectDir . '/public/' . $filename, 'fake-font-bytes');

        $font = (new Font())->setName('Roboto')->setWeight(700)->setStyle('normal')->setFilename($filename);

        $fontRepository = $this->createStub(FontRepository::class);
        $fontRepository->method('findBy')->willReturn([$font]);

        $contentExporter = $this->createMock(ContentExporter::class);
        $contentExporter->expects($this->once())
            ->method('export')
            ->with('site_font', $this->callback(function (array $items): bool {
                $this->assertSame('Roboto', $items[0]['name']);
                $this->assertSame(700, $items[0]['weight']);
                $this->assertSame('roboto-bold.woff2', $items[0]['originalFilename']);
                $this->assertArrayHasKey('file', $items[0]);
                $this->assertArrayNotHasKey('content', $items[0]);

                return true;
            }), $this->callback(function (array $files) use ($projectDir, $filename): bool {
                $this->assertCount(1, $files);
                $this->assertSame($projectDir . '/public/' . $filename, array_values($files)[0]);

                return true;
            }))
            ->willReturn(new BinaryFileResponse(tempnam(sys_get_temp_dir(), 'export_test_')));

        $parameterBag = $this->createStub(ContainerBagInterface::class);
        $parameterBag->method('get')->willReturnCallback(
            static fn (string $name) => 'kernel.project_dir' === $name ? $projectDir : null,
        );

        $controller = $this->createController(fontRepository: $fontRepository, contentExporter: $contentExporter);
        $controller->setContainer($this->createContainer([
            'security.authorization_checker' => $this->createAuthorizationChecker(true),
            'security.csrf.token_manager' => $this->createCsrfTokenManager(true),
            'parameter_bag' => $parameterBag,
        ]));

        $controller->exportSelection($this->createAdminContext(), new BatchActionDto('exportSelection', [1], Font::class, 'valid'));

        unlink($projectDir . '/public/' . $filename);
        rmdir($projectDir . '/public/uploads');
        rmdir($projectDir . '/public');
        rmdir($projectDir);
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
}
