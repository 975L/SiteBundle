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

// The measure body copy is laid out on, well under --body-max-width, which frames a page and never carries a line of text
class ReadingMeasureTest extends TestCase
{
    // As it reads once normalized: the space following each comma is squeezed out along with the rest
    private const MEASURE = 'max-width:var(--reading-max-width,min(75ch,90vw))';

    private const MEASURED_SELECTORS = ['.legal div', '.text', '.site-article'];

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
    public function testTheThreeReadingRulesShareOneTokenisedMeasure(string $file): void
    {
        $declarations = $this->declarationsOf($this->normalize($file), self::MEASURED_SELECTORS, $file);

        $this->assertStringContainsString(
            self::MEASURE,
            $declarations,
            sprintf('"%s" hardcodes the reading measure instead of reading its token, leaving a site no way to retune it.', $file)
        );
    }

    // A breakpoint restating the measure is what left the text against both edges: at a viewport of
    // exactly 800px the box took the full width and "margin: auto" had nothing left to split
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testNoBreakpointRestatesTheMeasureAsABareLength(string $file): void
    {
        // Anchored on a declaration position, so a legitimate "@media (max-width: 800px)" prelude does not trip it
        $this->assertDoesNotMatchRegularExpression(
            '/[{;]max-width:800px/',
            $this->normalize($file),
            sprintf('"%s" still sets a bare 800px somewhere, which outruns the viewport it is meant to sit inside.', $file)
        );
    }

    // Strips comments and collapses whitespace, so the same assertions hold on the minified sheet
    private function normalize(string $file): string
    {
        $path = dirname(__DIR__) . '/public/css/' . $file;
        $this->assertFileExists($path, sprintf('"%s" is missing, the sass has not been compiled.', $file));

        $css = (string) preg_replace('#/\*.*?\*/#s', '', (string) file_get_contents($path));
        $css = (string) preg_replace('/\s*([{};:,>])\s*/', '$1', $css);

        return (string) preg_replace('/\s+/', ' ', $css);
    }

    /**
     * The body of the one rule carrying every selector, the three being meant to stay in a single rule.
     *
     * @param list<string> $selectors
     */
    private function declarationsOf(string $css, array $selectors, string $file): string
    {
        preg_match_all('/([^{}]+)\{([^{}]*)\}/', $css, $matches, PREG_SET_ORDER);

        foreach ($matches as $match) {
            if ([] === array_diff($selectors, explode(',', trim($match[1])))) {
                return $match[2];
            }
        }

        $this->fail(sprintf('"%s" has no rule holding %s together.', $file, implode(', ', $selectors)));
    }
}
