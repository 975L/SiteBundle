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
use c975L\SiteBundle\Service\LegalModelPlaceholders;
use PHPUnit\Framework\TestCase;

class LegalModelPlaceholdersTest extends TestCase
{
    // The default: a model rendered any way at all, including a plain {% include %} in an app's own template,
    // prints the value and never a marker
    public function testValueIsResolvedOnTheSpot(): void
    {
        $this->assertSame('Acme &amp; Co', $this->placeholders()->value('site-name'));
    }

    // An unknown slug must not even leak its own name into the page
    public function testUnknownSlugYieldsNothing(): void
    {
        $this->assertSame('', $this->placeholders()->value('site-secret'));
    }

    // Only unit extraction asks for markers, so the screen can show them and a fingerprint stays stable
    public function testMarkersAreWrittenOnlyInsideWithMarkers(): void
    {
        $placeholders = $this->placeholders();

        $this->assertSame('%site-name%', $placeholders->withMarkers(static fn (): string => $placeholders->value('site-name')));
        $this->assertSame('Acme &amp; Co', $placeholders->value('site-name'));
    }

    // A throwing render must not leave every later page printing markers
    public function testMarkerModeIsClearedEvenWhenTheRenderThrows(): void
    {
        $placeholders = $this->placeholders();

        try {
            $placeholders->withMarkers(static fn (): string => throw new \RuntimeException('boom'));
        } catch (\RuntimeException) {
        }

        $this->assertSame('Acme &amp; Co', $placeholders->value('site-name'));
    }

    public function testSubstitutionEscapesPlainValues(): void
    {
        $this->assertSame(
            '<p>Acme &amp; Co</p>',
            $this->placeholders()->substitute('<p>%site-name%</p>'),
        );
    }

    // Rich text authored in ConfigBundle keeps its markup, and a postal address keeps its line breaks
    public function testSubstitutionKeepsRichValuesAndBreaksAddresses(): void
    {
        $html = $this->placeholders()->substitute('<p>%site-owner%</p><p>%site-hosting-provider%</p>');

        $this->assertStringContainsString('<strong>Owner</strong>', $html);
        $this->assertStringContainsString("Host<br />\nCity", $html);
    }

    // The whole point of D: a client-authored override goes through the same substitution as bundle text
    public function testSubstitutionAppliesToAnyHtmlNotJustTheBundleTemplates(): void
    {
        $this->assertSame(
            '<p>Our own clause, at Acme &amp; Co</p>',
            $this->placeholders()->substitute('<p>Our own clause, at %site-name%</p>'),
        );
    }

    // A marker no model ever emits stays inert text rather than reading a config value it was never shown
    public function testUnknownMarkerIsLeftUntouched(): void
    {
        $this->assertSame('%site-secret%', $this->placeholders()->substitute('%site-secret%'));
    }

    public function testSlugsAreListedForTheCustomizationScreen(): void
    {
        $this->assertContains('site-name', $this->placeholders()->slugs());
        $this->assertNotContains('site-secret', $this->placeholders()->slugs());
    }

    private function placeholders(): LegalModelPlaceholders
    {
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturnCallback(static fn (string $slug): ?string => match ($slug) {
            'site-name' => 'Acme & Co',
            'site-owner' => '<strong>Owner</strong>',
            'site-hosting-provider' => "Host\nCity",
            default => null,
        });

        return new LegalModelPlaceholders($config);
    }
}
