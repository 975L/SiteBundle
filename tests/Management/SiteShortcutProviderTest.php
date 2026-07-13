<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Controller\Management\SiteShortcutController;
use c975L\SiteBundle\Management\SiteShortcutProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class SiteShortcutProviderTest extends TestCase
{
    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

        return $translator;
    }

    private function createConfigService(bool $userRegistrationEnabled): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            static fn (string $key) => match ($key) {
                'user-registration-enabled' => $userRegistrationEnabled,
                'site-role-editor' => 'ROLE_EDITOR',
                'site-role-admin' => 'ROLE_ADMIN',
                default => null,
            }
        );

        return $configService;
    }

    // When registration is disabled, the shortcut offers to enable it and is not marked active
    public function testGetShortcutsOffersToEnableRegistrationWhenDisabled(): void
    {
        $provider = new SiteShortcutProvider($this->createTranslator(), $this->createConfigService(false));

        $shortcuts = $provider->getShortcuts();
        $registrationShortcut = $shortcuts[1];

        $this->assertSame('label.user_registration_enable', $registrationShortcut['label']);
        $this->assertFalse($registrationShortcut['active']);
        $this->assertSame(SiteShortcutController::REGISTRATION_ENABLED_TOGGLE_ROUTE, $registrationShortcut['route']);
    }

    // When registration is already enabled, the shortcut offers to disable it and is marked active
    public function testGetShortcutsOffersToDisableRegistrationWhenEnabled(): void
    {
        $provider = new SiteShortcutProvider($this->createTranslator(), $this->createConfigService(true));

        $registrationShortcut = $provider->getShortcuts()[1];

        $this->assertSame('label.user_registration_disable', $registrationShortcut['label']);
        $this->assertTrue($registrationShortcut['active']);
    }

    // The 4 shortcuts are always contributed, each with its dedicated route and role
    public function testGetShortcutsReturnsAllFourEntries(): void
    {
        $provider = new SiteShortcutProvider($this->createTranslator(), $this->createConfigService(false));

        $shortcuts = $provider->getShortcuts();

        $this->assertCount(4, $shortcuts);
        $this->assertSame('ROLE_EDITOR', $shortcuts[0]['role']);
        $this->assertSame('ROLE_SUPER_ADMIN', $shortcuts[2]['role']);
        $this->assertSame(SiteShortcutController::SITEMAP_CREATE_ROUTE, $shortcuts[2]['route']);
        $this->assertSame(SiteShortcutController::EXPORT_TABLES_ROUTE, $shortcuts[3]['route']);
    }
}
