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
    // The bundle's own stylesheet, the compiled theme variables file, and the third-party
    // cookie-consent stylesheet are all contributed, in that order (theme variables must come after
    // styles.min.css to win the cascade), when no page template is active
    public function testGetStylesheetsReturnsBundleThemeAndThirdPartyStylesheetsWhenNoTemplateActive(): void
    {
        $configService = $this->createMock(ConfigServiceInterface::class);
        $configService->expects($this->once())->method('get')->with('theme-stylesheet')->willReturn(null);

        $this->assertSame(
            [
                'bundles/c975lsite/css/styles.min.css',
                'bundles/build/site-theme.css',
                'https://cdnjs.cloudflare.com/ajax/libs/cookieconsent2/3.1.0/cookieconsent.min.css',
            ],
            (new StylesheetProvider($configService))->getStylesheets()
        );
    }

    // The active page template's stylesheet is inserted after site-theme.css (so it can override the
    // design tokens defined there) and before the third-party stylesheet
    public function testGetStylesheetsInsertsActiveTemplateStylesheet(): void
    {
        $configService = $this->createMock(ConfigServiceInterface::class);
        $configService->expects($this->once())->method('get')->with('theme-stylesheet')->willReturn('warm-artisan');

        $this->assertSame(
            [
                'bundles/c975lsite/css/styles.min.css',
                'bundles/build/site-theme.css',
                'bundles/c975lsite/css/page-templates/warm-artisan.min.css',
                'https://cdnjs.cloudflare.com/ajax/libs/cookieconsent2/3.1.0/cookieconsent.min.css',
            ],
            (new StylesheetProvider($configService))->getStylesheets()
        );
    }
}
