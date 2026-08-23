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

// The cookie banner and the Matomo snippet both live in core-bundle, which owns their configs, writes the legal text naming the instance and renders them from the layout this one extends
// What is caught here is a copy coming back: rendered again from the footer a site is free to override, they would either double up or vanish with it, and a guard re-added beside them would read a key this bundle no longer declares
class ThirdPartySnippetsTest extends TestCase
{
    // Rendered by the layout core-bundle owns, so a site overriding its footer keeps its tracking and its cookie banner
    public function testTheFooterRendersNeitherComponent(): void
    {
        $footer = $this->read('templates/components/General/Footer.html.twig');

        foreach (['c975LUi:Analytics:Matomo', 'c975LUi:Cookie:Consent'] as $tag) {
            $this->assertStringNotContainsString($tag, $footer, 'the layout renders it, a footer rendering it too doubles it on every page');
        }
    }

    // Neither the snippets nor their preconnect are written again here: the layout this one extends carries them for every site, whether or not it installs this bundle
    public function testTheLayoutRepeatsNothingOfThem(): void
    {
        $layout = $this->read('templates/layout.html.twig');

        foreach (['c975LUi:Analytics:Matomo', 'c975LUi:Cookie:Consent', 'site-matomo-url'] as $needle) {
            $this->assertStringNotContainsString($needle, $layout, 'core-bundle\'s layout already writes it');
        }
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
