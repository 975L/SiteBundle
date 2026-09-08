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

// A site uploading a logo drawn for a dark page has both images written by Navbar.html.twig, and the stylesheet is what paints one of the two - which one depends on the visitor's own system when theme-mode is "auto", so nothing but a media query can pick it. This locks that switch in both compiled sheets: dropped, the two logos would show at once on every page
class NavbarDarkLogoTest extends TestCase
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

    // Hidden by default, i.e. on the light page a site with no dark preference reads
    #[DataProvider('stylesheetProvider')]
    public function testTheDarkLogoIsHiddenOnALightPage(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/(?<![\w.\[=-])img\.menu-logo__on-dark\s*\{\s*display:\s*none/',
            $this->stylesheet($file),
            sprintf('"%s" no longer hides the dark logo by default, so both logos show on a light page.', $file)
        );
    }

    // The same two branches the dark palette is declared on, the theme fixed to dark and the "auto" mode following the visitor's system, hence two occurrences of each swap
    #[DataProvider('stylesheetProvider')]
    public function testBothLogosAreSwappedInBothDarkBranches(string $file): void
    {
        $css = $this->stylesheet($file);

        $this->assertSame(
            2,
            preg_match_all('/img\.menu-logo__on-light\s*\{\s*display:\s*none/', $css),
            sprintf('"%s" does not hide the light logo in both dark branches.', $file)
        );
        $this->assertSame(
            2,
            preg_match_all('/img\.menu-logo__on-dark\s*\{\s*display:\s*block/', $css),
            sprintf('"%s" does not show the dark logo in both dark branches.', $file)
        );
    }

    // Written as "img.class" and not on the class alone: ".menu-logo img" carries an element too, and would otherwise win over it and show both logos at once
    #[DataProvider('stylesheetProvider')]
    public function testTheSwitchOutweighsTheLogoSizingRule(string $file): void
    {
        $this->assertSame(
            0,
            preg_match_all('/(?<!img)\.menu-logo__on-(?:light|dark)\s*\{/', $this->stylesheet($file)),
            sprintf('"%s" carries the switch on the class alone, which ".menu-logo img" outweighs.', $file)
        );
    }

    private function stylesheet(string $file): string
    {
        $path = \dirname(__DIR__) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, recompile sass/styles.scss.', $file));

        return (string) file_get_contents($path);
    }
}
