<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Service\StylesheetProvider;
use PHPUnit\Framework\TestCase;

class StylesheetProviderTest extends TestCase
{
    // The bundle's own stylesheet and the compiled theme variables file are contributed, in that order (theme variables must come after styles.min.css to win the cascade), when no theme shape stylesheet is active. The cookie-consent library's own CSS is loaded dynamically by its Stimulus controller instead (see assets/js/cookie-consent.js), not listed here.
    public function testGetStylesheetsReturnsBundleAndThemeStylesheetsWhenNoShapeActive(): void
    {
        $configService = $this->createMock(ConfigServiceInterface::class);
        $configService->expects($this->once())->method('get')->with('theme-stylesheet')->willReturn(null);

        $this->assertSame(
            [
                'bundles/c975lsite/css/styles.min.css',
                'bundles/build/site-theme.css',
            ],
            (new StylesheetProvider($configService))->getStylesheets()
        );
    }

    // The active theme's shape stylesheet is inserted after site-theme.css, so it can override the design tokens defined there
    public function testGetStylesheetsInsertsActiveThemeStylesheet(): void
    {
        $configService = $this->createMock(ConfigServiceInterface::class);
        $configService->expects($this->once())->method('get')->with('theme-stylesheet')->willReturn('default');

        $this->assertSame(
            [
                'bundles/c975lsite/css/styles.min.css',
                'bundles/build/site-theme.css',
                'bundles/c975lsite/css/themes/default.min.css',
            ],
            (new StylesheetProvider($configService))->getStylesheets()
        );
    }
}
