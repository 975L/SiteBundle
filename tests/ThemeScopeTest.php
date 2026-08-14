<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests;

use PHPUnit\Framework\TestCase;

// A token is declared on ":root, [data-theme]", never on ":root" alone - the contract UiBundle's sass/_tokens.scss states and this bundle holds up
// A var() written in a custom property's value is substituted on the element carrying the declaration, not where the token is read: declared on ":root" only, every derived token (--title-color, --surface-alt, the --footer-* and --navbar-* pairs) resolves once against the root's palette and descends already computed, so an element opening an ambiance of its own repaints --background and --text and gets the rest computed for the ambiance it sits in
// Nothing fails when the selector narrows back: the page keeps rendering exactly as before and only a scoped ambiance goes silently wrong, which is why it is locked here
class ThemeScopeTest extends TestCase
{
    // Both compiled sheets, the minified one being what a site actually serves
    private const array COMPILED = ['public/css/styles.css', 'public/css/styles.min.css', 'public/css/emails.css'];

    public function testTheCompiledTokensAreDeclaredOnBothSelectors(): void
    {
        foreach (self::COMPILED as $file) {
            $this->assertMatchesRegularExpression(
                '/:root,\s*\[data-theme\]\s*\{/',
                $this->read($file),
                sprintf('"%s" declares its tokens on ":root" alone, so an element carrying data-theme reads the values computed for the ambiance it sits in.', $file)
            );
        }
    }

    // The dark half of the same contract: a scope asking for dark gets the dark tokens, and under an OS-dark preference a scope keeps them unless it asks for light - scoped to ":root", both would redeclare the light palette on a dark page
    public function testTheDarkTokensReachAScopeToo(): void
    {
        foreach (['public/css/styles.css', 'public/css/styles.min.css'] as $file) {
            $css = $this->read($file);

            $this->assertMatchesRegularExpression(
                '/(?<!:root)\[data-theme=["\']?dark["\']?\]\s*\{/',
                $css,
                sprintf('"%s" scopes the dark tokens to ":root", so an element opening a dark ambiance stays light.', $file)
            );
            $this->assertMatchesRegularExpression(
                '/\[data-theme\]:not\(\[data-theme=["\']?light["\']?\]\)\s*\{/',
                $css,
                sprintf('"%s" leaves the OS-preference branch on ":root" alone, so under a dark OS any data-theme scope falls back to light.', $file)
            );
        }
    }

    // The site's own theme file has to carry the same selector, or a value a design sets there loses to the bundle's default inside a scope: the default is declared on the very element the site's ":root" rule only reaches by inheritance
    public function testTheScaffoldedThemeCarriesTheSameSelector(): void
    {
        $this->assertMatchesRegularExpression(
            '/:root,\s*\[data-theme\]\s*\{/',
            $this->read('scaffold/assets/styles/themes/site.css'),
            'The scaffolded site.css declares on ":root" alone, so inside a scoped ambiance the design loses to the bundle default.'
        );
    }

    // The admin's palette stays on :root and is read through var(), which inherits: that indirection is what lets a scope redeclare the chain without falling back to the bundle's own colors
    public function testTheAdminPaletteIsStillReadThroughVar(): void
    {
        $sass = $this->read('sass/_variables.scss');

        foreach (['--primary: var(--c975l-color-primary', '--background: var(--c975l-color-background'] as $declaration) {
            $this->assertStringContainsString(
                $declaration,
                $sass,
                sprintf('"%s" is gone, so the backoffice palette no longer reaches a scoped ambiance.', $declaration)
            );
        }
    }

    private function read(string $file): string
    {
        $path = dirname(__DIR__) . '/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, so the contract it holds would go unchecked.', $file));

        return (string) file_get_contents($path);
    }
}
