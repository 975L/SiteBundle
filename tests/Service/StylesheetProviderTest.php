<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Service\StylesheetProvider;
use PHPUnit\Framework\TestCase;

class StylesheetProviderTest extends TestCase
{
    // The bundle's own stylesheet and the compiled theme variables file are contributed, in that order (theme variables must come after styles.min.css to win the cascade). The cookie-consent library's own CSS is loaded dynamically by its Stimulus controller instead (see assets/js/cookie-consent.js), not listed here.
    public function testGetStylesheetsReturnsBundleAndThemeStylesheets(): void
    {
        $this->assertSame(
            [
                'bundles/c975lsite/css/styles.min.css',
                'bundles/build/site-theme.css',
                'bundles/build/site-fonts-uploaded.css',
            ],
            (new StylesheetProvider())->getStylesheets()
        );
    }

    // The list is fixed: there is no theme catalog to pick a fourth "shape" stylesheet from anymore, a site's own tokens living in its assets/styles/themes/theme.css (loaded through the app's own asset pipeline, not contributed here)
    public function testGetStylesheetsReadsNoConfig(): void
    {
        $this->assertCount(3, (new StylesheetProvider())->getStylesheets());
    }
}
