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
    // Only the bundle's own stylesheet. The compiled theme variables and the uploaded fonts' @font-face sheet are both contributed by UiBundle, alongside the listeners that write them - so a site running Ui without this bundle still gets its theme.
    public function testGetStylesheetsReturnsBundleStylesheet(): void
    {
        $this->assertSame(
            [
                'bundles/c975lsite/css/styles.min.css',
            ],
            (new StylesheetProvider())->getStylesheets()
        );
    }

    // The list is fixed: there is no theme catalog to pick a second "shape" stylesheet from anymore, a site's own tokens living in its assets/styles/themes/*.css (loaded through the app's own asset pipeline, not contributed here)
    public function testGetStylesheetsReadsNoConfig(): void
    {
        $this->assertCount(1, (new StylesheetProvider())->getStylesheets());
    }
}
