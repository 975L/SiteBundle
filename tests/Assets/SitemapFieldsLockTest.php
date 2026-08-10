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

// Guards assets/js/sitemap-fields.js, which dims and blocks the sitemap fields a page's state makes meaningless - the repository has no browser to run it in, and the back-office layout carries a nonce on style-src, under which anything the controller writes to a style attribute is silently dropped
class SitemapFieldsLockTest extends TestCase
{
    private const CONTROLLER = 'assets/js/sitemap-fields.js';

    // .ui-field-locked comes from UiBundle's management.css (sass/management/_form-fields.scss), hence the c975l/core-bundle floor in composer.json
    public function testTheLockedStateIsCarriedByTheSharedClass(): void
    {
        $this->assertStringContainsString(
            "classList.toggle('ui-field-locked', locked)",
            $this->read(),
            'sitemap-fields.js no longer marks a locked row with the "ui-field-locked" class UiBundle styles.'
        );
    }

    // A style written from script never applies under the back-office nonce, so the row stayed fully bright and clickable
    public function testNoStyleIsWrittenFromScript(): void
    {
        $controller = $this->read();

        $this->assertStringNotContainsString('style.pointerEvents', $controller, 'sitemap-fields.js sets an inline style again, which the back-office style-src nonce drops.');
        $this->assertStringNotContainsString('style.opacity', $controller, 'sitemap-fields.js sets an inline style again, which the back-office style-src nonce drops.');
        $this->assertStringNotContainsString('setAttribute(\'style\'', $controller, 'sitemap-fields.js sets an inline style again, which the back-office style-src nonce drops.');
    }

    // "disabled" isn't submitted, so the locked values would be wiped on every save of a non-indexable page
    public function testTheLockedFieldsStaySubmitted(): void
    {
        $controller = $this->read();

        $this->assertStringContainsString('field.readOnly = locked;', $controller, 'sitemap-fields.js no longer keeps the locked fields readonly.');
        $this->assertStringNotContainsString('.disabled =', $controller, 'sitemap-fields.js disables the locked fields, whose values are then dropped on save.');
    }

    // The class alone says nothing to a screen reader, and the field would still be reachable by tab
    public function testTheLockedStateIsExposedToAssistiveTech(): void
    {
        $controller = $this->read();

        $this->assertStringContainsString("setAttribute('aria-disabled'", $controller, 'sitemap-fields.js no longer tells assistive tech a field is locked.');
        $this->assertStringContainsString('field.tabIndex = locked ? -1 : 0;', $controller, 'sitemap-fields.js leaves a locked field reachable by the keyboard.');
    }

    private function read(): string
    {
        $path = \dirname(__DIR__, 2) . '/' . self::CONTROLLER;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
