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

// --primary is a deep brand blue reading at 1.43:1 on the #121212 page, under the 3:1 even large text asks for. It is left alone as a surface (buttons, flats, the footer band, where the white --button-color needs it dark), so the ink read against the page is lightened instead - and in both dark branches, a site fixed to dark by its admin and one following the visitor's OS being the same page to read
class DarkThemeTextTokensTest extends TestCase
{
    // Every token painting text off the brand goes through --primary-ink (UiBundle's sass/_tokens.scss), so this file lightens that one and they all follow
    private const array TOKENS = ['--link-color', '--title-color', '--navbar-active-color', '--navbar-site-name-color'];

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

    // [data-theme="dark"] and the @media (prefers-color-scheme: dark) branch, i.e. the two occurrences each token below is counted in. Not scoped to :root, a scope opening a dark ambience getting the same tokens (see ThemeScopeTest)
    #[DataProvider('stylesheetProvider')]
    public function testBothDarkBranchesAreCompiled(string $file): void
    {
        $css = $this->stylesheet($file);

        $this->assertMatchesRegularExpression('/(?<!:root)\[data-theme=["\']?dark["\']?\]\s*\{/', $css, sprintf('"%s" no longer carries the fixed-dark branch.', $file));
        $this->assertStringContainsString('prefers-color-scheme: dark', $css, sprintf('"%s" no longer carries the OS-preference branch.', $file));
    }

    #[DataProvider('stylesheetProvider')]
    public function testEveryTokenPaintingTextIsLightenedInBothBranches(string $file): void
    {
        $css = $this->stylesheet($file);

        $matches = preg_match_all('/--primary-ink:\s*color-mix\(in srgb,\s*var\(--primary\)\s*\d+%,\s*#fff\)/', $css);

        $this->assertSame(
            2,
            $matches,
            sprintf('"%s" lightens --primary-ink in %d dark branch(es) instead of 2, so every ink reading it paints at --primary\'s 1.43:1 on the dark page.', $file, $matches)
        );

        foreach (self::TOKENS as $token) {
            $this->assertMatchesRegularExpression(
                sprintf('/%s:\s*var\(--primary-ink\)/', preg_quote($token, '/')),
                $css,
                sprintf('"%s" declares %s off --primary rather than --primary-ink, so the dark page never lightens it.', $file, $token)
            );
        }
    }

    // The other side of that rule: ink on a ground staying --primary in both modes is a stated white, never var(--white), which dark mode swaps with --black and left the footer and the mobile dropdown's labels near-black on their own hue
    #[DataProvider('stylesheetProvider')]
    public function testInkOnAColoredBandIsAStatedWhite(string $file): void
    {
        $css = (string) preg_replace('/\s+/', '', $this->stylesheet($file));

        $this->assertStringContainsString('--footer-text:#fff', $css, sprintf('"%s" has the footer band read a swappable token, which dark mode turns near-black on --primary.', $file));
        $this->assertMatchesRegularExpression('/\.menu-label\{[^}]*color:#fff/', $css, sprintf('"%s" has the mobile dropdown\'s labels read a swappable token, which dark mode turns near-black on --primary.', $file));
    }

    private function stylesheet(string $file): string
    {
        $path = \dirname(__DIR__) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, recompile sass/styles.scss.', $file));

        return (string) file_get_contents($path);
    }
}
