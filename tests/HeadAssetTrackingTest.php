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

// Turbo Drive never re-evaluates the <head>, so a visitor already on the site keeps the old assets after a deploy unless the tags say otherwise
class HeadAssetTrackingTest extends TestCase
{
    public function testTheStylesheetsAreTracked(): void
    {
        $this->assertMatchesRegularExpression(
            '/<link rel="stylesheet" href="\{\{ stylesheet \}\}" data-turbo-track="reload">/',
            $this->layout(),
            'The stylesheets lost their tracking attribute, so a deploy no longer reaches a visitor already on the site.'
        );
    }

    // The inline JSON of the <script type="importmap"> lists every digested JS url, so the tag alone fingerprints the whole graph
    public function testTheImportmapIsTracked(): void
    {
        $this->assertStringContainsString(
            "'data-turbo-track': 'reload'",
            $this->layout(),
            'The importmap lost its tracking attribute, so a deploy changing only the JS no longer reaches a visitor already on the site.'
        );
    }

    private function layout(): string
    {
        $path = dirname(__DIR__) . '/templates/layout.html.twig';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
