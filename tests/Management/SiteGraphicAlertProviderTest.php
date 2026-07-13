<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Management\SiteGraphicAlertProvider;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\MediaRepository;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class SiteGraphicAlertProviderTest extends TestCase
{
    // Builds a MediaRepository double whose findOneByRole() answers according to $existingRoles
    private function createMediaRepository(array $existingRoles = []): MediaRepository
    {
        $repository = $this->createStub(MediaRepository::class);
        $repository->method('findOneByRole')->willReturnCallback(
            static fn (string $role): ?Media => \in_array($role, $existingRoles, true) ? new Media() : null
        );

        return $repository;
    }

    private function createAdminUrlGenerator(): AdminUrlGeneratorInterface
    {
        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setController')->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('generateUrl')->willReturn('/admin/site-graphic/new');

        return $generator;
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

        return $translator;
    }

    // With none of the 4 site-wide graphics uploaded yet, every role raises an alert
    public function testGetAlertsReturnsOneAlertPerMissingRole(): void
    {
        $provider = new SiteGraphicAlertProvider($this->createMediaRepository(), $this->createAdminUrlGenerator(), $this->createTranslator());

        $alerts = $provider->getAlerts();

        $this->assertCount(4, $alerts);
        $this->assertSame('label.favicon', $alerts[0]['label']);
        $this->assertSame('/admin/site-graphic/new', $alerts[0]['url']);
    }

    // A role already having its Media uploaded must not raise an alert
    public function testGetAlertsSkipsRolesAlreadyUploaded(): void
    {
        $provider = new SiteGraphicAlertProvider(
            $this->createMediaRepository([Media::ROLE_FAVICON, Media::ROLE_LOGO]),
            $this->createAdminUrlGenerator(),
            $this->createTranslator()
        );

        $alerts = $provider->getAlerts();

        $this->assertCount(2, $alerts);
        $labels = array_column($alerts, 'label');
        $this->assertNotContains('label.favicon', $labels);
        $this->assertNotContains('label.logo', $labels);
    }

    // No missing graphic at all: no alert is raised
    public function testGetAlertsReturnsEmptyArrayWhenEveryRoleIsUploaded(): void
    {
        $allRoles = [Media::ROLE_FAVICON, Media::ROLE_APPLE_TOUCH_ICON, Media::ROLE_OG_IMAGE, Media::ROLE_LOGO];
        $provider = new SiteGraphicAlertProvider($this->createMediaRepository($allRoles), $this->createAdminUrlGenerator(), $this->createTranslator());

        $this->assertSame([], $provider->getAlerts());
    }
}
