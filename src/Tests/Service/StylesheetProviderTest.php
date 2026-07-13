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

// Lives under src/Tests (not a sibling tests/ dir) so it stays autoloadable by consuming apps,
// whose attribute route loader recursively reflects every class under the bundle root
class StylesheetProviderTest extends TestCase
{
    // The bundle's own stylesheet and the third-party cookie-consent stylesheet are both contributed
    public function testGetStylesheetsReturnsBundleAndThirdPartyStylesheets(): void
    {
        $this->assertSame(
            [
                'bundles/c975lsite/css/styles.min.css',
                'https://cdnjs.cloudflare.com/ajax/libs/cookieconsent2/3.1.0/cookieconsent.min.css',
            ],
            (new StylesheetProvider())->getStylesheets()
        );
    }
}
