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

// Paper is white and the ink is black, whatever the color mode the page was read in. The print sheet writes those two on the elements themselves rather than answering the site's own tokens: a theme file restates them at a higher specificity (:root[data-theme="dark"]) and later in the compiled stylesheet, so a token set here would lose. See sass/_print.scss.
class PrintInkTest extends TestCase
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

    #[DataProvider('stylesheetProvider')]
    public function testPrintingIsBlackOnWhiteWhateverTheColorMode(string $file): void
    {
        $print = $this->printBlock($file);

        $this->assertMatchesRegularExpression(
            '/html,body,\*,\*::before,\*::after\{color:#000!important/',
            $print,
            sprintf('"%s" no longer writes black ink on every printed element, so a dark-mode site prints its own light text.', $file)
        );
        $this->assertMatchesRegularExpression(
            '/html,body\{background-color:#fff!important/',
            $print,
            sprintf('"%s" no longer prints on a white ground, so a dark-mode site prints its own near-black page.', $file)
        );
    }

    // Backgrounds are dropped rather than repainted: a colored band, and the fixed dark voile a hero or a banner paints over its own picture, would otherwise carry black text on a dark ground
    #[DataProvider('stylesheetProvider')]
    public function testEveryPaintedGroundIsDroppedIncludingThePseudoElements(string $file): void
    {
        $print = $this->printBlock($file);

        $this->assertMatchesRegularExpression(
            // "transparent" in the expanded sheet, "rgba(0,0,0,0)" once the compressed one has rewritten it
            '/html,body,\*,\*::before,\*::after\{[^}]*background-color:(transparent|rgba\(0,0,0,0\))!important/',
            $print,
            sprintf('"%s" keeps the painted grounds when printing, which a black ink cannot be read on.', $file)
        );
        $this->assertStringNotContainsString(
            'var(--white)',
            $print,
            sprintf('"%s" reads --white when printing, a token dark mode swaps with --black.', $file)
        );
    }

    // The "@media print" block alone, so a rule found here is one that really applies on paper
    private function printBlock(string $file): string
    {
        $path = \dirname(__DIR__) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, recompile sass/styles.scss.', $file));

        $css = (string) preg_replace('/\s+/', '', (string) file_get_contents($path));
        $start = strpos($css, '@mediaprint{');
        $this->assertNotFalse($start, sprintf('"%s" carries no "@media print" block at all.', $file));

        $depth = 0;
        $length = \strlen($css);
        for ($i = (int) $start + \strlen('@mediaprint'); $i < $length; ++$i) {
            $depth += '{' === $css[$i] ? 1 : ('}' === $css[$i] ? -1 : 0);
            if (0 === $depth) {
                return substr($css, (int) $start, $i - (int) $start + 1);
            }
        }

        $this->fail(sprintf('"%s" leaves its "@media print" block unclosed.', $file));
    }
}
