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

// The navbar's own height and the room it keeps around its line, locked in the compiled stylesheets
class NavbarHeightTest extends TestCase
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

    // A blanket height on the "nav" element reached the menu navbar too and held it at 74px, its logo and burger pinned against the top edge whatever room the header asked for
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheFallbackNavbarStylesItselfOnItsOwnClass(string $file): void
    {
        $css = $this->stylesheet($file);

        $this->assertStringContainsString(
            '.nav-simple{text-align:center}',
            $css,
            sprintf('"%s" no longer styles the fallback navbar (see Navbar.html.twig).', $file)
        );
        $this->assertDoesNotMatchRegularExpression(
            '/(^|[},])nav\{/',
            $css,
            sprintf('"%s" styles the bare "nav" element again, which the menu navbar is one of.', $file)
        );
    }

    // Same value above and below, so the line reads centered rather than stuck to the top edge
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheHeaderKeepsTheSameRoomAboveAndBelowItsLine(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.menu-header\{[^}]*min-height:var\(--navbar-height\)[^}]*padding:var\(--navbar-padding-y\) 15px/',
            $this->stylesheet($file),
            sprintf('"%s" no longer holds the navbar to its own height, or drops the room around its line.', $file)
        );
    }

    // The burger is a flex item of the header: as a fixed one it resolved its top from the static position the flex line gave it, which sat above the viewport
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheBurgerStaysInTheHeaderFlow(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.menu-toggle\{(?![^}]*position:fixed)[^}]*height:var\(--navbar-content-height\)/',
            $this->stylesheet($file),
            sprintf('"%s" takes the burger out of the header flow, or lets it outgrow the bar.', $file)
        );
    }

    // Hangs off the navbar rather than off a hardcoded viewport offset, so it follows the bar's real height and stays attached to it once the page is scrolled
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheMobileDropdownHangsOffTheNavbar(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.menu \.menu-items\{[^}]*position:absolute;top:100%/',
            $this->stylesheet($file),
            sprintf('"%s" detaches the mobile dropdown from the navbar it drops from.', $file)
        );
    }

    // A fixed navbar gives back exactly what it took out of the flow, not an approximation of it
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testAFixedNavbarGivesItsHeightBackToThePage(string $file): void
    {
        $this->assertStringContainsString(
            'body.navbar-fixed{padding-top:var(--navbar-height)}',
            $this->stylesheet($file),
            sprintf('"%s" compensates a fixed navbar with something else than its height.', $file)
        );
    }

    // The bar has to stay on its floor once fixed, since that floor is what body.navbar-fixed gives back to the page
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testAFixedNavbarDropsTheTagline(string $file): void
    {
        $this->assertStringContainsString(
            '.menu-fixed .menu-site-tagline{display:none}',
            $this->stylesheet($file),
            sprintf('"%s" lets the tagline grow a fixed navbar past the height it gives back to the page.', $file)
        );
    }

    // "static" is not a containing block, so the dropdown hanging off the bar would resolve its top on the initial one instead
    public function testAStaticNavbarIsWrittenOutAsRelative(): void
    {
        $path = dirname(__DIR__) . '/templates/components/General/Navbar.html.twig';
        $this->assertFileExists($path);

        $this->assertStringContainsString(
            "{% set navbarPosition = navbarPosition == 'static' ? 'relative' : navbarPosition %}",
            (string) file_get_contents($path),
            'Navbar.html.twig lets "static" reach --navbar-position, which detaches the mobile dropdown from the bar.'
        );
    }

    // site-navbar-show-name used to be read on the menu branch only, so a site with no navbar blocks kept its name hidden whatever the config said
    public function testTheFallbackNavbarShowsTheSiteNameToo(): void
    {
        $path = dirname(__DIR__) . '/templates/components/General/Navbar.html.twig';
        $this->assertFileExists($path);

        $template = (string) file_get_contents($path);
        $fallback = substr($template, (int) strpos($template, '{% else %}'));

        $this->assertStringContainsString(
            '{% if showSiteName and siteName is not null %}',
            $fallback,
            'Navbar.html.twig drops site-navbar-show-name on the fallback navbar, where there is no menu brand to carry the name.'
        );
    }

    // Without it the name falls back to the --primary the fallback navbar hands its links, which the dark theme does not lighten
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheFallbackSiteNameKeepsTheSiteNameToken(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/\.nav-simple-name a,\.nav-simple-name a:hover\{color:var\(--navbar-site-name-color\)\}/',
            $this->stylesheet($file),
            sprintf('"%s" no longer colors the fallback navbar\'s site name with its own token.', $file)
        );
    }

    // The scaffolded site.css restates every chrome token at its default value, these three included
    public function testScaffoldedThemeRestatesTheTokens(): void
    {
        $path = dirname(__DIR__) . '/scaffold/assets/styles/themes/site.css';
        $this->assertFileExists($path);

        $theme = (string) file_get_contents($path);
        $tokens = [
            '--navbar-padding-y: 8px;',
            '--navbar-content-height: 44px;',
            '--navbar-height: calc(var(--navbar-content-height) + 2 * var(--navbar-padding-y));',
        ];

        foreach ($tokens as $token) {
            $this->assertStringContainsString($token, $theme, sprintf('The scaffolded theme does not restate "%s".', $token));
        }
    }

    // Strips comments, collapses whitespace and drops the last declaration's semicolon, so the same assertions hold on the minified sheet
    private function stylesheet(string $file): string
    {
        $path = dirname(__DIR__) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));
        $css = (string) preg_replace('/\s*([{};:,])\s*/', '$1', $css);

        return str_replace(';}', '}', $css);
    }
}
