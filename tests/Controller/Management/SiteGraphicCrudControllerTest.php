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
use c975L\SiteBundle\Controller\Management\SiteGraphicCrudController;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\MediaRepository;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Context\RequestContext;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Provider\AdminContextProviderInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Registry\AdminControllerRegistryInterface;
use EasyCorp\Bundle\EasyAdminBundle\Contracts\Router\AdminRouteGeneratorInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SiteGraphicCrudControllerTest extends TestCase
{
    // Builds a MediaRepository double whose role query answers with the roles already uploaded
    private function createMediaRepositoryReturning(array $usedRoles): MediaRepository
    {
        $query = $this->createStub(Query::class);
        $query->method('getSingleColumnResult')->willReturn($usedRoles);

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('select')->willReturnSelf();
        $queryBuilder->method('where')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $mediaRepository = $this->createStub(MediaRepository::class);
        $mediaRepository->method('createQueryBuilder')->willReturn($queryBuilder);

        return $mediaRepository;
    }

    // An admin context carrying the role a clicked button/alert asks the "new" form to pre-fill
    private function createAdminContextProvider(?string $requestedRole = null): AdminContextProvider
    {
        $request = new Request();
        if (null !== $requestedRole) {
            $request->query->set(SiteGraphicCrudController::ROLE_PARAMETER, $requestedRole);
        }

        $adminRequest = new Request();
        $adminRequest->attributes->set('easyadmin_context', AdminContext::forTesting(
            requestContext: RequestContext::forTesting($request)
        ));

        $requestStack = new RequestStack();
        $requestStack->push($adminRequest);

        return new AdminContextProvider($requestStack);
    }

    // AdminUrlGenerator is final - can't be mocked, so it's built for real with stubbed interface collaborators, matching how the framework itself wires it (same as MenuCrudControllerTest)
    private function createAdminUrlGenerator(string $generatedUrl = '/management/site-graphic/new'): AdminUrlGenerator
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

    private function createController(array $usedRoles = [], ?string $requestedRole = null): SiteGraphicCrudController
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnArgument(0);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('site-role-editor');

        return new SiteGraphicCrudController(
            $configService,
            $this->createMediaRepositoryReturning($usedRoles),
            $translator,
            $this->createAdminContextProvider($requestedRole),
            $this->createAdminUrlGenerator(),
        );
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

    // Reaches the list feeding the index's "missing graphic" buttons, only ever built for the index page
    private function missingRoles(SiteGraphicCrudController $controller): array
    {
        return (new \ReflectionMethod($controller, 'missingRoles'))->invoke($controller);
    }

    // --- configureFields: role ----------------------------------------------------------------------------

    // A singleton graphic already uploaded has no second row to create - it must not even be offered
    public function testConfigureFieldsRoleDropsSingletonRolesAlreadyUploaded(): void
    {
        $controller = $this->createController([Media::ROLE_FAVICON, Media::ROLE_LOGO]);

        $role = $this->findFieldByProperty($controller->configureFields(Crud::PAGE_NEW), 'role');
        $choices = $role->getAsDto()->getCustomOption(ChoiceField::OPTION_CHOICES);

        $this->assertSame(
            [Media::ROLE_APPLE_TOUCH_ICON, Media::ROLE_OG_IMAGE, Media::ROLE_ERROR_IMAGE],
            array_keys($choices)
        );
    }

    // Error images form a pool: the role stays selectable however many rows already carry it
    public function testConfigureFieldsRoleKeepsRepeatableRolesAlreadyUsed(): void
    {
        $controller = $this->createController([Media::ROLE_ERROR_IMAGE]);

        $role = $this->findFieldByProperty($controller->configureFields(Crud::PAGE_NEW), 'role');
        $choices = $role->getAsDto()->getCustomOption(ChoiceField::OPTION_CHOICES);

        $this->assertArrayHasKey(Media::ROLE_ERROR_IMAGE, $choices);
    }

    // A row's role is set once at creation and fixed for good, the file being saved under a name derived from it
    public function testConfigureFieldsRoleIsDisabledOnEditPage(): void
    {
        $role = $this->findFieldByProperty($this->createController()->configureFields(Crud::PAGE_EDIT), 'role');

        $this->assertTrue($role->getAsDto()->getFormTypeOptions()['disabled'] ?? null);
    }

    // Arriving from a button/alert, the role is already picked: nothing left to choose, only the file to upload
    public function testConfigureFieldsRoleIsDisabledWhenTheRequestAlreadyPicksIt(): void
    {
        $controller = $this->createController(requestedRole: Media::ROLE_LOGO);

        $role = $this->findFieldByProperty($controller->configureFields(Crud::PAGE_NEW), 'role');

        $this->assertTrue($role->getAsDto()->getFormTypeOptions()['disabled'] ?? null);
    }

    // Reached through the plain "new" action, the role is the admin's to pick
    public function testConfigureFieldsRoleStaysEnabledWithoutARequestedRole(): void
    {
        $role = $this->findFieldByProperty($this->createController()->configureFields(Crud::PAGE_NEW), 'role');

        $this->assertFalse($role->getAsDto()->getFormTypeOptions()['disabled'] ?? null);
    }

    // --- createEntity -------------------------------------------------------------------------------------

    // The whole point of the buttons: one click lands on the upload form with the role already carried by the entity, the disabled field having nothing to submit
    public function testCreateEntityPreFillsTheRequestedRole(): void
    {
        $media = $this->createController(requestedRole: Media::ROLE_FAVICON)->createEntity(Media::class);

        $this->assertInstanceOf(Media::class, $media);
        $this->assertSame(Media::ROLE_FAVICON, $media->getRole());
    }

    // Stale link (the graphic got uploaded in the meantime): the role is ignored and the admin picks from what is left
    public function testCreateEntityIgnoresARoleAlreadyUploaded(): void
    {
        $media = $this->createController([Media::ROLE_FAVICON], Media::ROLE_FAVICON)->createEntity(Media::class);

        $this->assertNull($media->getRole());
    }

    // A hand-crafted url can't smuggle in anything but a known role
    public function testCreateEntityIgnoresAnUnknownRole(): void
    {
        $media = $this->createController(requestedRole: 'banner')->createEntity(Media::class);

        $this->assertNull($media->getRole());
    }

    // --- missingRoles -------------------------------------------------------------------------------------

    // Nothing uploaded yet: the four singleton graphics each get their button, error-image being repeatable and never "missing"
    public function testMissingRolesListsEverySingletonGraphicNotUploadedYet(): void
    {
        $missing = $this->missingRoles($this->createController());

        $this->assertSame(
            ['label.favicon', 'label.apple_touch_icon', 'label.og_image', 'label.logo'],
            array_column($missing, 'label')
        );
        $this->assertSame('/management/site-graphic/new', $missing[0]['url']);
    }

    // Once every singleton graphic is uploaded, the index has nothing left to propose
    public function testMissingRolesIsEmptyWhenEverySingletonGraphicIsUploaded(): void
    {
        $controller = $this->createController([
            Media::ROLE_FAVICON,
            Media::ROLE_APPLE_TOUCH_ICON,
            Media::ROLE_OG_IMAGE,
            Media::ROLE_LOGO,
        ]);

        $this->assertSame([], $this->missingRoles($controller));
    }
}
