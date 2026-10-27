<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests;

use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// The installed app's own "About" dropdown: its downloads and the site's legal pages, written for any PWA site and shown in the app alone
class NavbarAppMenuTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function stylesheetProvider(): array
    {
        return [
            'styles.css' => ['styles.css'],
            'styles.min.css' => ['styles.min.css'],
        ];
    }

    // Guarded by the PWA alone: a site with no legal page still offers its downloads
    public function testTheMenuIsWrittenForAnyPwaSite(): void
    {
        $this->assertMatchesRegularExpression(
            '/\{% if pwa_enabled\(\) %\}\s*\{% set legalPages = site_legal_pages\(\) %\}\s*<div class="menu-item menu-dropdown menu-app-only"/',
            $this->navbar()
        );
    }

    // The downloads first, then one "menu_link" per legal page
    public function testTheMenuHoldsTheDownloadsAndTheLegalPages(): void
    {
        $menu = $this->appMenu();

        $this->assertStringContainsString("<a href=\"{{ path('ui_pwa_downloads') }}\" class=\"menu-link\">", $menu);
        $this->assertStringContainsString("{% for legalPage in legalPages %}\n                                {% include '@c975LSite/blocks/MenuLink.html.twig' with {target: 'page:' ~ legalPage.id} only %}", $menu);
        $this->assertLessThan(strpos($menu, '{% for legalPage in legalPages %}'), strpos($menu, "path('ui_pwa_downloads')"));
    }

    // Outside the standalone display mode the entry leaves the bar, on any screen
    #[DataProvider('stylesheetProvider')]
    public function testTheStylesheetHidesItOutsideTheInstalledApp(string $file): void
    {
        $path = \dirname(__DIR__) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, recompile sass/styles.scss.', $file));

        $this->assertMatchesRegularExpression(
            '/@media\s*not all and \(display-mode:\s*standalone\)\s*\{\s*\.menu \.menu-item\.menu-app-only\s*\{\s*display:\s*none[;}]/',
            (string) file_get_contents($path),
            sprintf('"%s" no longer keeps an "app-only" menu entry out of the site\'s own bar.', $file)
        );
    }

    private function navbar(): string
    {
        $path = \dirname(__DIR__) . '/templates/components/General/Navbar.html.twig';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    // From the dropdown's opening tag to the tools that follow the items
    private function appMenu(): string
    {
        $source = $this->navbar();
        $start = strpos($source, '<div class="menu-item menu-dropdown menu-app-only"');
        $this->assertNotFalse($start, 'The navbar no longer writes the "menu-app-only" dropdown.');

        return substr($source, $start, (int) strpos($source, '<div class="menu-tools">') - $start);
    }
}
