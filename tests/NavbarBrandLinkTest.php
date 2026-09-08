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

// The logo and the site name both point at the home page, on both navbar branches: two adjacent links had a screen reader announce that destination twice in a row, so they are wrapped in one
class NavbarBrandLinkTest extends TestCase
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

    public function testTheMenuBrandWrapsTheLogoAndTheNameInOneLink(): void
    {
        $template = $this->template();

        $this->assertStringContainsString('class="menu-brand-link"', $template);
        $this->assertStringContainsString('<span class="menu-site-name">{{ siteName }}</span>', $template);
        $this->assertStringNotContainsString('class="menu-site-name">{{ siteName }}</a>', $template);
    }

    public function testTheFallbackBarWrapsThemInOneLinkToo(): void
    {
        $template = $this->template();

        $this->assertStringContainsString('class="nav-simple-brand"', $template);
        $this->assertStringContainsString('<span class="nav-simple-name">{{ siteName }}</span>', $template);
    }

    // Either part on its own still gets the link: a site showing only its logo, and one showing only its name. "anyLogo" and not "logo": a site may carry the dark logo alone
    public function testEachBranchRendersTheBrandAsSoonAsEitherPartIsThere(): void
    {
        $this->assertSame(
            2,
            substr_count($this->template(), '{% if anyLogo is not null or showBrandName %}'),
            'Navbar.html.twig no longer opens the brand link on both branches.'
        );
    }

    // The two logos are written on both branches, each behind its own test: a site uploads the light one, the dark one, both, or neither
    public function testBothBranchesWriteEachLogoBehindItsOwnTest(): void
    {
        $template = $this->template();

        $this->assertSame(2, substr_count($template, '{% if logo is not null %}'));
        $this->assertSame(2, substr_count($template, '{% if logoOnDark is not null %}'));
    }

    // The classes the stylesheet switches on are only written when both were uploaded - carried by one logo alone, they would hide it on one of the two grounds
    public function testTheSwitchingClassesAreOnlyWrittenWhenBothLogosExist(): void
    {
        $template = $this->template();

        $this->assertSame(2, substr_count($template, '{% if hasLogoPair %} class="menu-logo__on-light"{% endif %}') + substr_count($template, '{% if hasLogoPair %} menu-logo__on-light{% endif %}'));
        $this->assertSame(2, substr_count($template, '{% if hasLogoPair %} class="menu-logo__on-dark"{% endif %}') + substr_count($template, '{% if hasLogoPair %} menu-logo__on-dark{% endif %}'));
        $this->assertStringContainsString('{% set hasLogoPair = logo is not null and logoOnDark is not null %}', $template);
    }

    // Both are inside the same link now: an alt on the image would have that link read the site name twice. Four, the pair of logos on each branch being written the same: the theme hiding one of the two must not take the name with it
    public function testTheLogoAltIsEmptiedWhenTheNameIsPrintedBesideIt(): void
    {
        $this->assertSame(
            4,
            substr_count($this->template(), 'alt="{{ showBrandName ? \'\' : siteName|default(\'Logo\') }}"'),
            'A navbar logo keeps its alt while the name is printed next to it, which reads the link twice.'
        );
    }

    // The empty alt says the image is decorative, aria-hidden says it on purpose - an accessibility checker reading the markup alone cannot tell an intentional alt="" from a forgotten one otherwise (see ConfigBundle's ContentQualityClient)
    public function testTheLogoIsMarkedDecorativeWhenTheNameIsPrintedBesideIt(): void
    {
        $this->assertSame(
            4,
            substr_count($this->template(), '{% if showBrandName %} aria-hidden="true"{% endif %}'),
            'A navbar logo is left without aria-hidden while the name is printed next to it.'
        );
    }

    // Only the dark twin of a pair is deferred: a browser leaves a hidden lazy image alone until it is shown, so a light-themed visitor never fetches it. Standing alone it is the painted logo, and takes back the priority the light one has
    public function testOnlyTheHiddenTwinOfAPairIsDeferred(): void
    {
        $template = $this->template();

        $this->assertSame(
            2,
            substr_count($template, '{{ hasLogoPair ? \' loading="lazy"\' : \' loading="eager" fetchpriority="high"\' }}'),
            'The dark navbar logo no longer defers to its twin, so a light-themed visitor fetches an image no theme paints for them.'
        );
        $this->assertSame(
            2,
            substr_count($template, ' loading="eager" fetchpriority="high">'),
            'The light navbar logo is no longer fetched first, though it is what a page above the fold paints by default.'
        );
    }

    // _links.scss underlines every hovered link, which would now be drawn through the name when the logo is the part being hovered
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testEachStylesheetKeepsTheBrandLinksUnderlineOff(string $file): void
    {
        $css = $this->stylesheet($file);

        foreach (['menu-brand-link', 'nav-simple-brand'] as $class) {
            $this->assertStringContainsString(
                sprintf('.%1$s:hover,.%1$s:focus,.%1$s:visited:hover{text-decoration:none}', $class),
                $css,
                sprintf('"%s" underlines ".%s" on hover, drawing the line through the site name.', $file, $class)
            );
        }
    }

    // Declared on the <span> itself now: the --link-color the generic hover rule sets on the wrapping link would otherwise be inherited straight through it
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testTheSiteNameKeepsItsOwnTokenOnBothBars(string $file): void
    {
        $css = $this->stylesheet($file);

        foreach (['menu-site-name', 'nav-simple-name'] as $class) {
            $this->assertMatchesRegularExpression(
                sprintf('/\.%s\{[^}]*color:var\(--navbar-site-name-color\)/', $class),
                $css,
                sprintf('"%s" no longer colors ".%s" with the navbar\'s own token.', $file, $class)
            );
        }
    }

    private function template(): string
    {
        $path = \dirname(__DIR__) . '/templates/components/General/Navbar.html.twig';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }

    // Strips comments and collapses whitespace, so the same assertions hold on the minified sheet
    private function stylesheet(string $file): string
    {
        $path = \dirname(__DIR__) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));
        $css = (string) preg_replace('/\s*([{};:,])\s*/', '$1', $css);

        // The expanded sheet keeps the last declaration's semicolon, which the minifier drops
        return str_replace(';}', '}', $css);
    }
}
