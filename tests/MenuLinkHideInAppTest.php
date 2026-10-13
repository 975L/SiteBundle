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

// A menu entry ticked "hide in the installed app": written for everyone with a marker class, the display mode being known to the browser alone - see MenuLinkTypeTest for the checkbox itself
class MenuLinkHideInAppTest extends TestCase
{
    /**
     * @return array<string, array{string}>
     */
    public static function templateProvider(): array
    {
        return [
            'MenuLink' => ['MenuLink.html.twig'],
            'MenuDropdown' => ['MenuDropdown.html.twig'],
        ];
    }

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

    #[DataProvider('templateProvider')]
    public function testTheTemplateAddsTheClassWhenTheBoxIsTicked(string $file): void
    {
        $path = \dirname(__DIR__) . '/templates/blocks/' . $file;
        $this->assertFileExists($path);

        $this->assertMatchesRegularExpression(
            '/hideInApp\|default\(false\) \? \' ?menu-item--web-only\'/',
            (string) file_get_contents($path),
            sprintf('"%s" no longer writes the "menu-item--web-only" class, so the checkbox does nothing.', $file)
        );
    }

    // Inside the standalone media query alone, or the entry would leave the site's own bar too
    #[DataProvider('stylesheetProvider')]
    public function testTheStylesheetHidesItInTheInstalledAppOnly(string $file): void
    {
        $path = \dirname(__DIR__) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, recompile sass/styles.scss.', $file));

        $this->assertMatchesRegularExpression(
            '/@media\s*\(display-mode:\s*standalone\)\s*\{\s*\.menu \.menu-item\.menu-item--web-only\s*\{\s*display:\s*none[;}]/',
            (string) file_get_contents($path),
            sprintf('"%s" no longer hides a "web-only" menu entry in the installed app.', $file)
        );
    }
}
