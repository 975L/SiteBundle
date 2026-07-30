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

// The 100vw full-bleeds stick out by half a scrollbar on each side and are swallowed at the root, locked in the compiled stylesheets
class HorizontalOverflowTest extends TestCase
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
    public function testTheRootClipsItsHorizontalOverflow(string $file): void
    {
        $this->assertStringContainsString(
            'overflow-x:clip',
            $this->stylesheet($file),
            sprintf('"%s" no longer swallows the full-bleeds, leaving the page scrollable sideways by half a scrollbar.', $file)
        );
    }

    // "hidden" swallows it too, but it also makes the root a scroll container of its own and pins overflow-y
    #[\PHPUnit\Framework\Attributes\DataProvider('stylesheetProvider')]
    public function testNoRuleFallsBackOnHidden(string $file): void
    {
        $this->assertStringNotContainsString(
            'overflow-x:hidden',
            $this->stylesheet($file),
            sprintf('"%s" is back on "hidden", which turns the root into a scroll container of its own.', $file)
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
