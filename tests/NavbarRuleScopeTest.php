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

// A footer builds its rows out of the very same "menu-item"/"menu-link"/"menu-label" blocks the navbar does, so every navbar rule left unscoped paints it too - with the navbar's own tokens, which a footer has its own pair of
class NavbarRuleScopeTest extends TestCase
{
    // Each reads a --navbar-* token or draws the dropdown's own chrome, neither of which a footer wants
    private const array SCOPED = [
        '.menu .menu-item',
        '.menu .menu-item.active',
        '.menu .menu-link:hover .menu-label',
        '.menu .menu-link:focus .menu-label',
        '.menu .menu-label',
    ];

    // Both repaint the pill's own label at the very weight the pill's rule carries, (0,3,0), so only source order keeps the pill's color on top
    private const array OUTWEIGHED_BY_ORDER = [
        '.menu .menu-label',
        '.menu.is-scrolled .menu-label',
    ];

    // The bare form of each rule above that must not be left behind - ".menu-label" is out, the base rule sizing every label the site over deliberately carrying no scope
    private const array NEVER_BARE = [
        '.menu-item',
        '.menu-item.active',
        '.menu-link:hover .menu-label',
        '.menu-link:focus .menu-label',
    ];

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

    #[DataProvider('stylesheetProvider')]
    public function testTheNavbarRulesAreScopedToTheMenu(string $file): void
    {
        $css = $this->stylesheet($file);

        foreach (self::SCOPED as $selector) {
            $this->assertStringContainsString(
                $selector,
                $css,
                sprintf('"%s" no longer scopes "%s" to the navbar, so the rule paints a footer link too.', $file, $selector)
            );
        }
    }

    // The unscoped hover is what used to beat the footer's own rule, weighing (0,3,0) against its (0,2,2)
    #[DataProvider('stylesheetProvider')]
    public function testNoneOfThemIsLeftUnscoped(string $file): void
    {
        $css = $this->stylesheet($file);

        foreach (self::NEVER_BARE as $selector) {
            $this->assertDoesNotMatchRegularExpression(
                sprintf('/(^|[,}])\s*%s\s*[,{]/', preg_quote($selector, '/')),
                $css,
                sprintf('"%s" still carries "%s" unscoped, which reaches the footer\'s own links.', $file, $selector)
            );
        }
    }

    // Deliberately left unscoped: its border reset is a no-op outside the navbar, and its margin is what spaces a footer's own "primary" pill
    #[DataProvider('stylesheetProvider')]
    public function testThePrimaryPillStaysUnscoped(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/(^|[,}])\s*\.menu-item--primary\s*\{/',
            $this->stylesheet($file),
            sprintf('"%s" scoped ".menu-item--primary" to the navbar, so a footer pill loses the spacing it had.', $file)
        );
    }

    // Tying equals, the last rule written wins: move the pill's label above either of the two and its --primary fill carries the navbar's text color again, unreadable wherever the two are close in lightness
    #[DataProvider('stylesheetProvider')]
    public function testThePillLabelIsPaintedLast(string $file): void
    {
        $css = $this->stylesheet($file);

        $pill = strpos($css, '.menu .menu-item--primary .menu-label');
        $this->assertNotFalse($pill, sprintf('"%s" no longer paints ".menu .menu-item--primary .menu-label", so the pill reads the navbar\'s text color.', $file));

        foreach (self::OUTWEIGHED_BY_ORDER as $selector) {
            $position = strrpos($css, $selector);
            $this->assertNotFalse($position, sprintf('"%s" no longer carries "%s".', $file, $selector));
            $this->assertGreaterThan(
                $position,
                $pill,
                sprintf('"%s" now writes "%s" after the pill\'s own label, which takes --navbar-btn-color out of the cascade at every desktop width.', $file, $selector)
            );
        }
    }

    private function stylesheet(string $file): string
    {
        $path = \dirname(__DIR__) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, recompile sass/styles.scss.', $file));

        return (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));
    }
}
