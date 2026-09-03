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

// A heading and a link at rest were both painted --primary directly, which left a design no way to move either without repainting the brand - and kept the lightened value the dark page gives them (see DarkThemeTextTokensTest) from ever reaching them
class TextInkTokensTest extends TestCase
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

    // Both default to what they replace, --primary-ink itself defaulting to --primary in UiBundle, so nothing moves until a design says otherwise
    #[DataProvider('stylesheetProvider')]
    public function testRootOffersBothTokensAtTheirPreviousValue(string $file): void
    {
        $css = $this->stylesheet($file);

        foreach (['--title-color' => 'var(--primary-ink)', '--link-hover-color' => 'var(--link-color)'] as $token => $value) {
            $this->assertMatchesRegularExpression(
                sprintf('/%s:\s*%s/', preg_quote($token, '/'), preg_quote($value, '/')),
                $css,
                sprintf('"%s" no longer declares %s as %s, so a site upgrading to it does not read as it did.', $file, $token, $value)
            );
        }
    }

    // h1-h6 read the token rather than the brand, --primary being a surface everywhere else
    #[DataProvider('stylesheetProvider')]
    public function testEveryHeadingIsPaintedWithTheTitleToken(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/h6\s*\{[^}]*color:\s*var\(--title-color\)/',
            $this->stylesheet($file),
            sprintf('"%s" no longer paints the headings with --title-color, so a page cannot set its titles in another ink.', $file)
        );
    }

    // The scaffolded site.css restates every chrome token at its default value, the three inks included - a copy kept by hand, so it drifts off --primary-ink on its own
    public function testScaffoldedThemeRestatesTheInksOnTheToken(): void
    {
        $path = \dirname(__DIR__) . '/scaffold/assets/styles/themes/site.css';
        $this->assertFileExists($path);

        $css = (string) file_get_contents($path);

        foreach (['--title-color', '--navbar-active-color', '--navbar-site-name-color'] as $token) {
            $this->assertStringContainsString(
                $token . ': var(--primary-ink);',
                $css,
                sprintf('The scaffolded site.css states %s off --primary rather than --primary-ink, so it hands a new site a default a dark page never lightens.', $token)
            );
        }
    }

    // The link at rest, which read --primary while --link-color only ever moved the hover
    #[DataProvider('stylesheetProvider')]
    public function testALinkAtRestReadsTheLinkToken(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/a,\s*h1 a\s*\{[^}]*color:\s*var\(--link-color\)/',
            $this->stylesheet($file),
            sprintf('"%s" no longer paints a link at rest with --link-color, so setting that token moves the hover alone.', $file)
        );
    }

    // And the hover, which now has a token of its own rather than borrowing the one naming the link's color
    #[DataProvider('stylesheetProvider')]
    public function testAHoveredLinkReadsTheHoverToken(string $file): void
    {
        $this->assertMatchesRegularExpression(
            '/a:hover,\s*a:visited:hover\s*\{[^}]*color:\s*var\(--link-hover-color\)/',
            $this->stylesheet($file),
            sprintf('"%s" no longer paints a hovered link with --link-hover-color.', $file)
        );
    }

    private function stylesheet(string $file): string
    {
        $path = \dirname(__DIR__) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, recompile sass/styles.scss.', $file));

        return (string) file_get_contents($path);
    }
}
