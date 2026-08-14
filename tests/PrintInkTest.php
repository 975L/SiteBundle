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
    // The reset's own selector, as it reads once every space has been stripped out of the sheet - ".print-exact *" losing its combinator along the way. Held here rather than spelled out four times, the three assertions below all having to follow it together
    private const string RESET_SELECTOR = 'html,body,*:not(.print-exact,.print-exact*),*:not(.print-exact,.print-exact*)::before,*:not(.print-exact,.print-exact*)::after{';

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
            '/' . preg_quote(self::RESET_SELECTOR, '/') . 'color:#000!important/',
            $print,
            sprintf('"%s" no longer writes black ink on every printed element, so a dark-mode site prints its own light text.', $file)
        );
        $this->assertMatchesRegularExpression(
            '/html,body\{[^}]*background-color:#fff!important/',
            $print,
            sprintf('"%s" no longer prints on a white ground, so a dark-mode site prints its own near-black page.', $file)
        );

        // Both rules land on "html" with the same specificity and "!important", so the white paint only wins by coming last
        $this->assertGreaterThan(
            strpos($print, self::RESET_SELECTOR),
            strpos($print, 'html,body{'),
            sprintf('"%s" paints the paper white before the reset drops the grounds, which leaves it transparent.', $file)
        );
    }

    // Backgrounds are dropped rather than repainted: a colored band, and the fixed dark voile a hero or a banner paints over its own picture, would otherwise carry black text on a dark ground
    #[DataProvider('stylesheetProvider')]
    public function testEveryPaintedGroundIsDroppedIncludingThePseudoElements(string $file): void
    {
        $print = $this->printBlock($file);

        $this->assertMatchesRegularExpression(
            // "transparent" in the expanded sheet, "rgba(0,0,0,0)" once the compressed one has rewritten it
            '/' . preg_quote(self::RESET_SELECTOR, '/') . '[^}]*background-color:(transparent|rgba\(0,0,0,0\))!important/',
            $print,
            sprintf('"%s" keeps the painted grounds when printing, which a black ink cannot be read on.', $file)
        );
        $this->assertStringNotContainsString(
            'var(--white)',
            $print,
            sprintf('"%s" reads --white when printing, a token dark mode swaps with --black.', $file)
        );
    }

    // A design whose ground is the content itself - a playing card, a certificate, a coupon - opts its subtree out of the reset and prints as it reads on screen. Without it the ink rules above, "!important" on the universal selector, are unreachable by any rule a site or another bundle could write
    #[DataProvider('stylesheetProvider')]
    public function testAPrintExactSubtreeKeepsItsOwnInkAndGrounds(string $file): void
    {
        $print = $this->printBlock($file);

        $this->assertStringContainsString(
            '*:not(.print-exact,.print-exact*)',
            $print,
            sprintf('"%s" resets the ink on every element without exception, so a design meant to be printed as it reads comes out of the press blank.', $file)
        );

        // Excluding the subtree is not enough on its own: a browser drops backgrounds on paper of its own accord, whatever the stylesheet left in place
        $this->assertMatchesRegularExpression(
            '/\.print-exact,\.print-exact\*\{print-color-adjust:exact/',
            $print,
            sprintf('"%s" leaves the browser free to drop the grounds of a print-exact subtree, which is what that subtree is printed for.', $file)
        );
    }

    // Two ways of writing the same intent that are not the same rule at all: on "*" a percentage font-size resolves against the parent and multiplies itself at each level of nesting, and a hardcoded sheet size pins the document to an A4 whatever format the site prints on
    #[DataProvider('stylesheetProvider')]
    public function testThePaperIsSizedByThePageAndTheTypeStepsDownOnlyOnce(string $file): void
    {
        $print = $this->printBlock($file);

        // The expanded sheet closes its last declaration with a semicolon where the compressed one drops it
        $this->assertMatchesRegularExpression(
            '/html\{font-size:95%;?\}/',
            $print,
            sprintf('"%s" no longer steps the type down on the root alone: written on "*" it compounds, and a label eight levels deep prints a third smaller than a heading.', $file)
        );
        $this->assertStringNotContainsString(
            '*{font-size:95%',
            $print,
            sprintf('"%s" steps the type down on every element, which compounds at each level of nesting.', $file)
        );
        $this->assertStringNotContainsString(
            '210mm',
            $print,
            sprintf('"%s" pins the printed document to an A4 sheet, which "@page" already sizes - and which a site printing a card or a label does not have.', $file)
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
