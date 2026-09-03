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

// --primary is the brand color as a fill, painted on the page; --primary-ink is that same color as ink, read against it. The two part company in dark mode, where sass/_theme-dark.scss lightens the ink and leaves the fill its hue, so a rule writing text, an outline or a rule with --primary stays the dark brand hue on a dark ground. UiBundle carries the same scan over its own sass, this one covering the site's chrome
class PrimaryInkRoleTest extends TestCase
{
    // The properties that put a color on the page as ink rather than as a fill
    private const string INK_PROPERTIES = 'color|outline|outline-color|border(?:-[a-z-]+)?|text-decoration-color|text-emphasis-color|column-rule-color|caret-color|fill|stroke|-webkit-text-fill-color';

    // Every ink role goes through --primary-ink, so the dark palette reaches all of them at once
    public function testNoInkRoleReadsThePrimaryFill(): void
    {
        $scanned = 0;
        $offences = [];
        $root = \strlen(\dirname(__DIR__) . '/sass/');

        foreach ($this->sassFiles() as $path) {
            foreach (explode("\n", (string) file_get_contents($path)) as $number => $line) {
                if (1 !== preg_match('/^\s*(' . self::INK_PROPERTIES . ')\s*:\s*(.+?;)(?:\s*\/\/.*)?\s*$/', $line, $match)) {
                    continue;
                }

                ++$scanned;
                if (str_contains($match[2], 'var(--primary)')) {
                    $offences[] = sprintf('sass/%s:%d — %s', substr($path, $root), $number + 1, trim($line));
                }
            }
        }

        $this->assertGreaterThan(50, $scanned, 'No ink declaration found under sass/: the directory moved, and this test would pass blind.');

        $this->assertSame([], $offences, sprintf(
            "These write, outline or rule with the --primary fill instead of the --primary-ink token, so they stay the dark brand hue on a dark ground:\n- %s",
            implode("\n- ", $offences)
        ));
    }

    /**
     * Walked rather than globbed, so any directory opened under sass/ later is covered too.
     *
     * @return string[]
     */
    private function sassFiles(): array
    {
        $files = [];
        $iterator = new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator(\dirname(__DIR__) . '/sass'));

        foreach ($iterator as $file) {
            if ('scss' === $file->getExtension()) {
                $files[] = $file->getPathname();
            }
        }

        $this->assertNotSame([], $files, 'No stylesheet found under sass/: the directory moved, and this test would pass blind.');

        return $files;
    }
}
