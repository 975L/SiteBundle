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

// Guards assets/controllers-admin.js, the back-office Stimulus barrel - the repository has no browser to load it in, so a register line pointing at a file that isn't shipped only breaks in a consuming app, and it breaks the whole admin app at once
class AdminControllersRegistrationTest extends TestCase
{
    private const string ADMIN_BARREL = 'assets/controllers-admin.js';

    // An import of a missing file aborts the module before startStimulusApp(), taking every other back-office controller down with it
    public function testEveryImportedControllerIsShipped(): void
    {
        preg_match_all("#from '\./(js/[^']+\.js)';#", $this->read(self::ADMIN_BARREL), $matches);
        $this->assertNotSame([], $matches[1], 'The admin barrel imports no controller at all.');

        foreach ($matches[1] as $path) {
            $this->assertFileExists(\dirname(__DIR__, 2) . '/assets/' . $path, sprintf('The admin barrel imports "%s", which the bundle does not ship', $path));
        }
    }

    // A register line whose class is never imported leaves the identifier bound to undefined, and Stimulus fails on the first element carrying it
    public function testEveryRegisteredControllerIsImported(): void
    {
        $barrel = $this->read(self::ADMIN_BARREL);
        preg_match_all("#app\.register\('[^']+', ([A-Za-z]+)\);#", $barrel, $matches);
        $this->assertNotSame([], $matches[1], 'The admin barrel registers no controller at all.');

        foreach ($matches[1] as $class) {
            $this->assertStringContainsString(sprintf('import %s from ', $class), $barrel, sprintf('The admin barrel registers "%s" without importing it', $class));
        }
    }

    // Moved to UiBundle, whose own admin app is loaded on every back-office page - registering it here too would have both apps answer the same identifier, and PageCrudController still writes it as a plain data-controller string
    public function testTheTitleConfirmControllerIsLeftToUiBundle(): void
    {
        $barrel = $this->read(self::ADMIN_BARREL);

        $this->assertFileDoesNotExist(\dirname(__DIR__, 2) . '/assets/js/title-confirm.js');
        $this->assertStringNotContainsString("app.register('title-confirm'", $barrel, 'The admin barrel registers "title-confirm" again, which UiBundle already ships.');
        $this->assertStringNotContainsString('js/title-confirm.js', $barrel, 'The admin barrel imports "title-confirm", which UiBundle already ships.');
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__, 2) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
