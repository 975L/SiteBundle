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

// The gap before the footer is a token like every other footer property, locked in the compiled stylesheets
class FooterMarginTopTest extends TestCase
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

    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testFooterReadsItsMarginTopToken(string $file): void
    {
        $css = $this->stylesheet($file);

        $this->assertStringContainsString(
            'margin-top:var(--footer-margin-top)',
            $css,
            sprintf('"%s" hardcodes the footer\'s top margin instead of reading its token.', $file)
        );

        $this->assertStringContainsString(
            '--footer-margin-top:3em',
            $css,
            sprintf('"%s" does not declare the token\'s default value, leaving the footer with no gap.', $file)
        );
    }

    // The scaffolded site.css restates every chrome token at its default value, this one included
    public function testScaffoldedThemeRestatesTheToken(): void
    {
        $path = dirname(__DIR__) . '/scaffold/assets/styles/themes/site.css';
        $this->assertFileExists($path);

        $this->assertStringContainsString('--footer-margin-top: 3em;', (string) file_get_contents($path));
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
