<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Management\SiteThemePresetProvider;
use PHPUnit\Framework\TestCase;

class SiteThemePresetProviderTest extends TestCase
{
    // Reads every config/themes/*.json shipped by the bundle into one preset per file, keyed by filename
    public function testGetPresetsReturnsOnePresetPerJsonFile(): void
    {
        $files = glob(\dirname(__DIR__, 2) . '/config/themes/*.json');

        $presets = (new SiteThemePresetProvider())->getPresets();

        $this->assertCount(\count($files), $presets);
        $this->assertArrayHasKey('default', $presets);
        $this->assertSame('label.theme_preset_default', $presets['default']['label']);
        $this->assertSame('rgb(11, 55, 178)', $presets['default']['values']['theme-color-primary']);
    }

    // "default" carries no demo page-template: preview()'s previewBlocks stays null for it, page
    // content is left untouched
    public function testGetPresetsResolvesPageTemplateToNullWhenNotDeclared(): void
    {
        $presets = (new SiteThemePresetProvider())->getPresets();

        $this->assertNull($presets['default']['pageTemplate']);
    }

    // "warm-artisan" declares a demo page-template (config/page-templates/agency-home-warm.json) so
    // ?preset=warm-artisan previews its full intended arrangement, not just colors/fonts/shape
    public function testGetPresetsResolvesPageTemplateWhenDeclared(): void
    {
        $presets = (new SiteThemePresetProvider())->getPresets();

        $this->assertSame('agency-home-warm', $presets['warm-artisan']['pageTemplate']);
    }
}
