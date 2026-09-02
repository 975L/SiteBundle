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

// What SitemapFieldsBehaviourTest cannot see about assets/js/sitemap-fields.js: it mounts markup of its own, so a class the controller writes is a class it also invents, and a style silently dropped by the back-office nonce looks in a scenario exactly like a style that applied
// Everything the scenarios do prove - the readonly, the aria, the tab order, the dimming - was taken out of here rather than asserted twice
class SitemapFieldsLockTest extends TestCase
{
    private const string CONTROLLER = 'assets/js/sitemap-fields.js';

    // The class comes from UiBundle (sass/management/_form-fields.scss), hence the c975l/core-bundle floor in composer.json: a scenario sees the class land on the row, never that anything paints it
    public function testTheLockedRowIsPaintedByTheClassUiBundleShips(): void
    {
        $stylesheet = \dirname(__DIR__, 2) . '/vendor/c975l/core-bundle/UiBundle/public/css/management.css';
        $this->assertFileExists($stylesheet, 'The back-office stylesheet this bundle locks its rows with is not installed.');

        $this->assertStringContainsString(
            '.ui-field-locked',
            (string) file_get_contents($stylesheet),
            'UiBundle no longer paints ".ui-field-locked", so a locked row is marked with a class nothing draws and looks fully editable.'
        );
    }

    // A style written from script never applies under the back-office nonce, so the row would stay bright and clickable while reading as locked
    public function testNoStyleIsWrittenFromScript(): void
    {
        $controller = $this->read();

        $this->assertStringNotContainsString('style.pointerEvents', $controller, 'sitemap-fields.js sets an inline style again, which the back-office style-src nonce drops.');
        $this->assertStringNotContainsString('style.opacity', $controller, 'sitemap-fields.js sets an inline style again, which the back-office style-src nonce drops.');
        $this->assertStringNotContainsString('setAttribute(\'style\'', $controller, 'sitemap-fields.js sets an inline style again, which the back-office style-src nonce drops.');
    }

    // The scenario proves the fields still leave with the form; this proves no future edit reaches for the one attribute that would stop them
    public function testTheLockIsNeverATrueDisabling(): void
    {
        $this->assertStringNotContainsString('.disabled =', $this->read(), 'sitemap-fields.js disables the locked fields, whose values are then dropped on save.');
    }

    private function read(): string
    {
        $path = \dirname(__DIR__, 2) . '/' . self::CONTROLLER;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
