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

// The footer sits at the bottom of the viewport on a page too short to fill it, locked in the compiled stylesheets
class StickyFooterTest extends TestCase
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

    // svh over dvh, so a retracting mobile URL bar doesn't make the footer jump
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testBodyIsAFullHeightFlexColumn(string $file): void
    {
        $css = $this->stylesheet($file);

        foreach (['min-height:100vh', 'min-height:100svh', 'display:flex', 'flex-direction:column'] as $declaration) {
            $this->assertStringContainsString(
                $declaration,
                $css,
                sprintf('"%s" lost "%s", so the footer no longer holds the bottom of a short page.', $file, $declaration)
            );
        }
    }

    // Shrink stays at 0, so a page longer than the viewport is never compressed
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testMainTakesTheFreeSpaceLeft(string $file): void
    {
        $this->assertStringContainsString(
            'main{flex:1 0 auto}',
            $this->stylesheet($file),
            sprintf('"%s" does not let main grow, leaving the free space between the navbar and the content.', $file)
        );
    }

    // Flex items don't collapse margins, so the last block's bottom margin would add up to the footer's own top one
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testLastContentMarginIsDroppedWhenTheFooterFollowsMain(string $file): void
    {
        // Regex rather than a plain substring: the unminified sheet keeps the selector's own spaces
        $this->assertMatchesRegularExpression(
            '/main:has\(\+\s*footer\)\s*>\s*:last-child\{margin-bottom:0\}/',
            $this->stylesheet($file),
            sprintf('"%s" lets the last block\'s bottom margin stack on top of the footer\'s own gap.', $file)
        );
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
