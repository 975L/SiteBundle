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

// The cookie banner and the Matomo snippet both live in core-bundle, which owns their configs and writes the legal text naming the instance - this bundle only renders them, and each carries its own "is it enabled" guard
// Re-adding a guard here is the silent failure to catch: it reads a key this bundle no longer declares, so a typo in it disables the snippet without a word rather than raising anything
class ThirdPartySnippetsTest extends TestCase
{
    public function testTheFooterRendersBothComponentsUnguarded(): void
    {
        $footer = $this->read('templates/components/General/Footer.html.twig');

        foreach (['<twig:c975LUi:Analytics:Matomo />', '<twig:c975LUi:Cookie:Consent />'] as $tag) {
            $this->assertStringContainsString($tag, $footer);
        }

        foreach (['site-enable-matomo', 'site-enable-cookie-consent'] as $slug) {
            $this->assertStringNotContainsString(\sprintf("config('%s')", $slug), $footer, 'the guard belongs to the component, not here');
        }
    }

    // The origin is preconnected from this layout because this is the one writing the <head> when SiteBundle is installed - core-bundle's own shell does the same for the site it serves alone
    public function testTheLayoutStillPreconnectsTheMatomoOrigin(): void
    {
        $layout = $this->read('templates/layout.html.twig');

        $this->assertStringContainsString("config('site-matomo-url')|split('/')|slice(0, 3)|join('/')", $layout);
        $this->assertStringContainsString('preconnectUrls|merge([matomoOrigin])', $layout);
    }

    // Declared where it is read: the component and its three keys left with it, so a copy staying here would shadow core-bundle's own and drift from the label an admin reads
    public function testTheBundleNoLongerDeclaresTheMatomoKeys(): void
    {
        $declared = array_column(json_decode($this->read('config/configs.json'), true, 512, \JSON_THROW_ON_ERROR), 'slug');

        foreach (['site-matomo-url', 'site-matomo-id', 'site-enable-matomo'] as $slug) {
            $this->assertNotContains($slug, $declared, \sprintf('"%s" is core-bundle\'s since v8.4, see UPGRADE.md.', $slug));
        }
    }

    private function read(string $relativePath): string
    {
        $path = \dirname(__DIR__) . '/' . $relativePath;
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
