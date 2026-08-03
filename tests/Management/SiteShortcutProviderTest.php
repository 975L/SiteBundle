<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\ConfigBundle\Management\ShortcutProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Controller\Management\SiteShortcutController;
use c975L\SiteBundle\Management\SiteShortcutProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class SiteShortcutProviderTest extends TestCase
{
    private function createProvider(): SiteShortcutProvider
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id): string => $id);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            static fn (string $key) => match ($key) {
                'site-role-editor' => 'ROLE_EDITOR',
                'site-role-admin' => 'ROLE_ADMIN',
                default => null,
            }
        );

        return new SiteShortcutProvider($translator, $configService);
    }

    // The only shortcut left here: "Regenerate sitemap" is contributed by ConfigBundle (which owns SitemapWriter), and so are the table export and the registration toggle since the account flow moved there
    public function testGetShortcutsContributesThePageCreationEntryAlone(): void
    {
        $shortcuts = $this->createProvider()->getShortcuts();

        $this->assertCount(1, $shortcuts);
        $this->assertSame('label.create_page', $shortcuts[0]['label']);
        $this->assertSame(SiteShortcutController::CREATE_PAGE_ROUTE, $shortcuts[0]['route']);
        $this->assertSame('ROLE_EDITOR', $shortcuts[0]['role']);
        $this->assertFalse($shortcuts[0]['active']);
    }

    // The dashboard groups shortcuts by category
    public function testGetShortcutsCategorizesItsEntryUnderSite(): void
    {
        $this->assertSame(ShortcutProviderInterface::CATEGORY_SITE, $this->createProvider()->getShortcuts()[0]['category']);
    }
}
