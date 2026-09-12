<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Assets;

use PHPUnit\Framework\TestCase;

// startStimulusApp() also registers whatever the consuming app's controllers.json enables, "live" among them, so a page loading several barrels each starting their own built those controllers once per barrel - read off the source, the repository having no browser to catch it in
class StimulusAppSharingTest extends TestCase
{
    private const array BARRELS = ['assets/controllers.js', 'assets/controllers-admin.js'];

    public function testEveryBarrelJoinsTheSharedApplication(): void
    {
        foreach (self::BARRELS as $barrel) {
            $source = $this->read($barrel);

            $this->assertStringContainsString(
                'globalThis.c975lStimulusApp ??= startStimulusApp()',
                $source,
                sprintf('"%s" starts an application of its own instead of joining the page\'s.', $barrel)
            );
        }
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
