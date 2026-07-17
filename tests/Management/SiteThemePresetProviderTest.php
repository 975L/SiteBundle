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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SiteThemePresetProviderTest extends TestCase
{
    private function createProvider(): SiteThemePresetProvider
    {
        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters): string => '/pages/' . $parameters['page'] . '/preview?preset=' . $parameters['preset']
        );

        return new SiteThemePresetProvider($urlGenerator);
    }

    // Reads every config/themes/*.json shipped by the bundle into one preset per file, keyed by filename
    public function testGetPresetsReturnsOnePresetPerJsonFile(): void
    {
        $files = glob(\dirname(__DIR__, 2) . '/config/themes/*.json');

        $presets = $this->createProvider()->getPresets();

        $this->assertCount(\count($files), $presets);
        $this->assertArrayHasKey('default', $presets);
        $this->assertSame('label.theme_preset_default', $presets['default']['label']);
    }

    // These presets are SiteBundle's own: ThemeCrudController (ConfigBundle) must translate their
    // label in SiteBundle's domain, not its own 'config' domain
    public function testGetPresetsDeclaresOwnTranslationDomain(): void
    {
        $presets = $this->createProvider()->getPresets();

        $this->assertSame('site', $presets['default']['domain']);
    }

    // "default" is a real theme, not just an empty fallback: it has its own shape stylesheet
    // (sass/themes/default.scss), materialized as a real preset instead of an implicit fallback
    public function testDefaultPresetDeclaresItsOwnStylesheet(): void
    {
        $presets = $this->createProvider()->getPresets();

        $this->assertSame('default', $presets['default']['stylesheet']);
    }

    // Every preset gets a ready-to-use preview link (page_preview route on the home page) so an
    // editor can judge its look before committing to "Apply preset" - a callable, not an
    // already-generated string, so the router is never called eagerly (see ThemePresetProviderInterface)
    public function testGetPresetsBuildsPreviewUrl(): void
    {
        $presets = $this->createProvider()->getPresets();

        $this->assertIsCallable($presets['default']['previewUrl']);
        $this->assertSame('/pages/home/preview?preset=default', ($presets['default']['previewUrl'])());
    }
}
