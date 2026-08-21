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

// The scroll buttons sit in the bottom corner, exactly where another bundle fixes a bar of its own (ShopBundle's basket bar): the room that bar takes is published as --bottom-bar-height on the body, and read here, so neither bundle knows the other's classes. A hardcoded offset puts the buttons back behind the bar, which no test in that other bundle would catch
class BottomBarOffsetTest extends TestCase
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

    // The fallback is what keeps the corner at 25px on a site fixing nothing to the bottom, the token being declared by no one then
    #[DataProvider('stylesheetProvider')]
    public function testTheScrollButtonsStepOverABottomBar(string $file): void
    {
        $this->assertStringContainsString(
            'bottom:calc(25px + var(--bottom-bar-height,0px))',
            $this->stylesheet($file),
            sprintf('"%s" hardcodes the scroll buttons\' offset instead of reading --bottom-bar-height, so they hide behind a bar fixed at the bottom of the viewport.', $file)
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
