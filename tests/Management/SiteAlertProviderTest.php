<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Controller\Management\PageCrudController;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\SiteAlertProvider;
use c975L\SiteBundle\Repository\PageRepository;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class SiteAlertProviderTest extends TestCase
{
    // A site holding both legal pages and serving its home has nothing to say - an alert raised on a healthy site is what makes an admin stop reading the dashboard
    public function testASiteWithBothLegalPagesAndAPublishedHomeRaisesNothing(): void
    {
        $provider = $this->createProvider(
            ['france/legal-notice' => true, 'france/privacy-policy' => true, 'france/cookies' => true],
            $this->createHomeStatus(isPublished: true),
        );

        $this->assertSame([], $provider->getAlerts());
    }

    // The label names which one is missing, the two queries being what tells them apart
    public function testEachMissingLegalPageRaisesItsOwnAlert(): void
    {
        $provider = $this->createProvider(
            ['france/legal-notice' => false, 'france/privacy-policy' => true, 'france/cookies' => true],
            null,
        );

        $alerts = $provider->getAlerts();

        $this->assertCount(1, $alerts);
        $this->assertSame('label.legal_notice', $alerts[0]['label']);
        $this->assertSame('label.legal_page_missing', $alerts[0]['description']);
        $this->assertSame(Config::SEVERITY_WARNING, $alerts[0]['severity']);
        // An admin who cannot edit a page can do nothing about any of this
        $this->assertSame('ROLE_EDITOR', $alerts[0]['role']);
    }

    public function testEveryMissingLegalPageRaisesItsOwnAlert(): void
    {
        $provider = $this->createProvider(
            ['france/legal-notice' => false, 'france/privacy-policy' => false, 'france/cookies' => false],
            null,
        );

        $this->assertSame(['label.legal_notice', 'label.privacy_policy', 'label.cookies'], array_column($provider->getAlerts(), 'label'));
    }

    // The one case worth a word: the page is there, the site answers 404 on "/" all the same
    public function testAnUnpublishedHomePageRaisesAnAlert(): void
    {
        $provider = $this->createProvider(
            ['france/legal-notice' => true, 'france/privacy-policy' => true, 'france/cookies' => true],
            $this->createHomeStatus(isPublished: false),
        );

        $alerts = $provider->getAlerts();

        $this->assertCount(1, $alerts);
        $this->assertSame('label.home_page', $alerts[0]['label']);
        $this->assertSame('label.home_page_unpublished', $alerts[0]['description']);
    }

    // A deleted page is no more served than an unpublished one, and its slug is still taken
    public function testADeletedHomePageRaisesTheSameAlert(): void
    {
        $provider = $this->createProvider(
            ['france/legal-notice' => true, 'france/privacy-policy' => true, 'france/cookies' => true],
            $this->createHomeStatus(isPublished: true, isDeleted: true),
        );

        $this->assertCount(1, $provider->getAlerts());
    }

    // "Missing" on a page that exists is a wrong word, and the creation form it used to link to has the admin write a duplicate instead of republishing
    public function testAnUnpublishedLegalPageRaisesTheUnpublishedAlertOnItsOwnForm(): void
    {
        $adminUrlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/admin/page/7/edit');
        $adminUrlGenerator->expects($this->once())->method('setAction')->with(Action::EDIT)->willReturnSelf();
        $adminUrlGenerator->expects($this->once())->method('setEntityId')->with(7)->willReturnSelf();

        $provider = $this->createProvider(
            ['france/legal-notice' => false, 'france/privacy-policy' => true, 'france/cookies' => true],
            null,
            $adminUrlGenerator,
            ['france/legal-notice' => 7],
        );

        $alerts = $provider->getAlerts();

        $this->assertCount(1, $alerts);
        $this->assertSame('label.legal_page_unpublished', $alerts[0]['description']);
        $this->assertSame('/admin/page/7/edit', $alerts[0]['url']);
    }

    // No "home" Page at all is a supported way to run - the app answers "/" from a route of its own, and nagging about it would be a false alert on every such site
    public function testNoHomePageAtAllRaisesNothing(): void
    {
        $provider = $this->createProvider(
            ['france/legal-notice' => true, 'france/privacy-policy' => true, 'france/cookies' => true],
            null,
        );

        $this->assertSame([], $provider->getAlerts());
    }

    // The target is what nothing else checks: renaming the CRUD compiles, and the alert only breaks under an admin's click
    public function testTheAlertsLinkToThePageCrud(): void
    {
        $adminUrlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setAction')->willReturnSelf();
        $adminUrlGenerator->method('setEntityId')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/admin/page/new');
        $adminUrlGenerator->expects($this->once())
            ->method('setController')
            ->with(PageCrudController::class)
            ->willReturnSelf()
        ;

        $provider = $this->createProvider(
            ['france/legal-notice' => false, 'france/privacy-policy' => true, 'france/cookies' => true],
            null,
            $adminUrlGenerator,
        );

        $this->assertSame('/admin/page/new', $provider->getAlerts()[0]['url']);
    }

    // The home alert opens that very page's form, where the publication switch is, rather than the list
    public function testTheHomeAlertLinksToThatPagesOwnForm(): void
    {
        $adminUrlGenerator = $this->createMock(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/admin/page/12/edit');
        $adminUrlGenerator->expects($this->once())->method('setAction')->with(Action::EDIT)->willReturnSelf();
        $adminUrlGenerator->expects($this->once())->method('setEntityId')->with(12)->willReturnSelf();

        $provider = $this->createProvider(
            ['france/legal-notice' => true, 'france/privacy-policy' => true, 'france/cookies' => true],
            $this->createHomeStatus(isPublished: false, id: 12),
            $adminUrlGenerator,
        );

        $this->assertSame('/admin/page/12/edit', $provider->getAlerts()[0]['url']);
    }

    /**
     * @param array<string, bool>                                     $legalModels            whether a published page carries each model
     * @param array<string, int>                                      $unpublishedLegalModels id of the page carrying each model while unpublished or trashed
     * @param array{id: int, isPublished: bool, isDeleted: bool}|null $home
     */
    private function createProvider(array $legalModels, ?array $home, ?AdminUrlGeneratorInterface $adminUrlGenerator = null, array $unpublishedLegalModels = []): SiteAlertProvider
    {
        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findByLegalModels')->willReturnCallback(
            static fn (array $models): array => ($legalModels[$models[0]] ?? false) ? ['page'] : []
        );
        $pageRepository->method('findByLegalModelsAnyStatus')->willReturnCallback(
            fn (array $models): array => isset($unpublishedLegalModels[$models[0]])
                ? [$this->createPage(isPublished: false, id: $unpublishedLegalModels[$models[0]])]
                : []
        );
        $pageRepository->method('findHomePublicationStatus')->willReturn($home);

        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('ROLE_EDITOR');

        return new SiteAlertProvider(
            $pageRepository,
            $adminUrlGenerator ?? $this->createUrlGenerator(),
            $translator,
            $configService,
        );
    }

    private function createUrlGenerator(): AdminUrlGeneratorInterface
    {
        $adminUrlGenerator = $this->createStub(AdminUrlGeneratorInterface::class);
        $adminUrlGenerator->method('unsetAll')->willReturnSelf();
        $adminUrlGenerator->method('setController')->willReturnSelf();
        $adminUrlGenerator->method('setAction')->willReturnSelf();
        $adminUrlGenerator->method('setEntityId')->willReturnSelf();
        $adminUrlGenerator->method('generateUrl')->willReturn('/admin/page');

        return $adminUrlGenerator;
    }

    private function createPage(bool $isPublished, bool $isDeleted = false, int $id = 1): Page
    {
        $page = $this->createStub(Page::class);
        $page->method('isPublished')->willReturn($isPublished);
        $page->method('isDeleted')->willReturn($isDeleted);
        $page->method('getId')->willReturn($id);

        return $page;
    }

    /**
     * The three scalars findHomePublicationStatus() selects, rather than a hydrated Page.
     *
     * @return array{id: int, isPublished: bool, isDeleted: bool}
     */
    private function createHomeStatus(bool $isPublished, bool $isDeleted = false, int $id = 1): array
    {
        return ['id' => $id, 'isPublished' => $isPublished, 'isDeleted' => $isDeleted];
    }
}
