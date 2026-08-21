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

// --icon-filter is UiBundle's, read by its own images sheet and defaulting to "none"; the dark value is this bundle's to give, dark mode living here in full. An icon laid on the page itself - a basket count, a list marker - otherwise keeps the black fill of its file on the #121212 page
class DarkIconFilterTest extends TestCase
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

    // Both dark branches, a site fixed to dark by its admin and one following the visitor's OS being the same page to read (see DarkThemeTextTokensTest)
    #[DataProvider('stylesheetProvider')]
    public function testTheIconFilterIsFilledInBothDarkBranches(string $file): void
    {
        $matches = substr_count($this->stylesheet($file), '--icon-filter:brightness(0) invert(1)');

        $this->assertSame(2, $matches, sprintf('"%s" whitens the page\'s icons in %d dark branch(es) instead of 2.', $file, $matches));
    }

    // Flattened before being inverted, so a two-tone file turns into one white silhouette rather than its own negative
    #[DataProvider('stylesheetProvider')]
    public function testTheFilterIsNotABareInversion(string $file): void
    {
        $this->assertStringNotContainsString(
            '--icon-filter:invert(1)',
            $this->stylesheet($file),
            sprintf('"%s" inverts the page\'s icons without flattening them first, turning a colored one into its negative.', $file)
        );
    }

    // Strips comments and collapses whitespace, so the same assertions hold on the minified sheet
    private function stylesheet(string $file): string
    {
        $path = dirname(__DIR__) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));

        return (string) preg_replace('/\s*([{};:,])\s*/', '$1', $css);
    }
}
