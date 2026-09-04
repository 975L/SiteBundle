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
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\SiteBundle\Service\SiteSnippetBuilder;
use c975L\UiBundle\Contract\SameAsProviderInterface;
use c975L\UiBundle\Registry\SameAsRegistry;
use PHPUnit\Framework\TestCase;

class SiteSnippetBuilderTest extends TestCase
{
    /**
     * @param array<string, string> $configs
     */
    private function builder(array $configs, ?SameAsRegistry $registry = null, string $defaultLocale = 'fr'): SiteSnippetBuilder
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(static fn (string $slug) => $configs[$slug] ?? null);

        return new SiteSnippetBuilder($configService, new SiteLocales([], $defaultLocale), $registry ?? new SameAsRegistry());
    }

    private function provider(array $urls): SameAsProviderInterface
    {
        $provider = $this->createStub(SameAsProviderInterface::class);
        $provider->method('getSameAs')->willReturn($urls);

        return $provider;
    }

    // The two nodes a home page publishes, tied by the "@id" the WebSite names its publisher with rather than describing it twice
    public function testTheGraphHoldsAnOrganizationAndTheWebSiteItPublishes(): void
    {
        $snippet = $this->builder(['site-name' => 'Éditions Test', 'site-url' => 'https://exemple.com'])->build();

        $this->assertSame('https://schema.org', $snippet['@context']);
        $this->assertSame(['Organization', 'WebSite'], array_column($snippet['@graph'], '@type'));
        $this->assertSame('https://exemple.com/#organization', $snippet['@graph'][0]['@id']);
        $this->assertSame(['@id' => 'https://exemple.com/#organization'], $snippet['@graph'][1]['publisher']);
    }

    // A trailing slash typed into "site-url" would otherwise double in every "@id" and every url of the graph
    public function testATrailingSlashInTheConfiguredUrlIsNotDoubled(): void
    {
        $snippet = $this->builder(['site-name' => 'Éditions Test', 'site-url' => 'https://exemple.com/'])->build();

        $this->assertSame('https://exemple.com/', $snippet['@graph'][0]['url']);
        $this->assertSame('https://exemple.com/#website', $snippet['@graph'][1]['@id']);
    }

    // No name or no url, nothing to publish: an Organization is identified by the first and both "@id" are built on the second
    public function testNothingIsPublishedWhileTheSiteHasNoNameOrNoUrl(): void
    {
        $this->assertSame([], $this->builder(['site-url' => 'https://exemple.com'])->build());
        $this->assertSame([], $this->builder(['site-name' => 'Éditions Test'])->build());
        $this->assertSame('', $this->builder([])->buildJson());
    }

    // The same rule as the other builders of the ecosystem: an unfilled setting is dropped from the graph instead of published blank
    public function testUnfilledSettingsAreDroppedFromTheGraph(): void
    {
        $snippet = $this->builder([
            'site-name' => 'Éditions Test',
            'site-url' => 'https://exemple.com',
            'site-contact-email' => '   ',
            'site-author' => '',
        ])->build();

        $this->assertSame(['@type', '@id', 'name', 'url', 'inLanguage', 'publisher'], array_keys($snippet['@graph'][1]));
        $this->assertArrayNotHasKey('email', $snippet['@graph'][0]);
        $this->assertArrayNotHasKey('founder', $snippet['@graph'][0]);
    }

    // The person an organization was founded by is a node and not a label, so a search engine reads a person
    public function testTheConfiguredAuthorIsPublishedAsTheFounder(): void
    {
        $snippet = $this->builder([
            'site-name' => 'Éditions Test',
            'site-url' => 'https://exemple.com',
            'site-author' => 'Fabien Catvez',
        ])->build();

        $this->assertSame(['@type' => 'Person', 'name' => 'Fabien Catvez'], $snippet['@graph'][0]['founder']);
    }

    // A one-person structure fills "site-author" with its own name: an Organization founded by an identically named Person is a claim nobody meant to make
    public function testAnAuthorRepeatingTheSiteNameIsNotPublishedAsItsOwnFounder(): void
    {
        $snippet = $this->builder([
            'site-name' => '975L',
            'site-url' => 'https://exemple.com',
            'site-author' => '975L',
        ])->build();

        $this->assertArrayNotHasKey('founder', $snippet['@graph'][0]);
    }

    // A personal site is about the person it names: "site-author" is its subject, not somebody standing behind it, and there is no organization for anyone to have founded
    public function testAPersonalSitePublishesThePersonItIsAbout(): void
    {
        $snippet = $this->builder([
            'site-name' => 'Laurent Marquet',
            'site-url' => 'https://exemple.com',
            'site-author' => 'Laurent Marquet',
            'site-schema-type' => 'Person',
        ])->build('https://exemple.com/logo.webp');

        $this->assertSame('Person', $snippet['@graph'][0]['@type']);
        $this->assertSame('https://exemple.com/#person', $snippet['@graph'][0]['@id']);
        $this->assertSame(['@id' => 'https://exemple.com/#person'], $snippet['@graph'][1]['publisher']);
        $this->assertArrayNotHasKey('founder', $snippet['@graph'][0]);
        // schema.org gives an Organization a "logo" and a Person an "image": the same file, under the property its own type reads
        $this->assertSame('https://exemple.com/logo.webp', $snippet['@graph'][0]['image']);
        $this->assertArrayNotHasKey('logo', $snippet['@graph'][0]);
    }

    // The WebSite keeps the site's own name whatever the publisher is called - a CV titled with a person's name and a site named after its author are not the same node
    public function testAPersonalSiteNamesThePersonAndTheWebSiteKeepsTheSiteName(): void
    {
        $snippet = $this->builder([
            'site-name' => 'Le carnet de Laurent',
            'site-url' => 'https://exemple.com',
            'site-author' => 'Laurent Marquet',
            'site-schema-type' => 'Person',
        ])->build();

        $this->assertSame('Laurent Marquet', $snippet['@graph'][0]['name']);
        $this->assertSame('Le carnet de Laurent', $snippet['@graph'][1]['name']);
    }

    // Anything else - an unfilled setting, a value typed by hand - falls back on the type most sites are
    public function testAnUnknownTypeFallsBackOnOrganization(): void
    {
        $snippet = $this->builder([
            'site-name' => 'Éditions Test',
            'site-url' => 'https://exemple.com',
            'site-schema-type' => 'Bakery',
        ])->build();

        $this->assertSame('Organization', $snippet['@graph'][0]['@type']);
    }

    // An agency selling a service says so, and keeps the Organization fragment its subtype belongs to - a "@id" a search engine has already seen must not move because the subtype was refined
    public function testAnOrganizationSubtypeKeepsTheOrganizationFragment(): void
    {
        $snippet = $this->builder([
            'site-name' => '975L',
            'site-url' => 'https://exemple.com',
            'site-schema-type' => 'ProfessionalService',
        ])->build();

        $this->assertSame('ProfessionalService', $snippet['@graph'][0]['@type']);
        $this->assertSame('https://exemple.com/#organization', $snippet['@graph'][0]['@id']);
        $this->assertSame(['@id' => 'https://exemple.com/#organization'], $snippet['@graph'][1]['publisher']);
    }

    // The property that ties the site and its social accounts into one entity, contributed by whichever bundle owns the profiles
    public function testContributedProfilesArePublishedAsSameAs(): void
    {
        $registry = new SameAsRegistry();
        $registry->addProvider($this->provider(['https://bsky.app/profile/exemple']));

        $snippet = $this->builder(['site-name' => 'Éditions Test', 'site-url' => 'https://exemple.com'], $registry)->build();

        $this->assertSame(['https://bsky.app/profile/exemple'], $snippet['@graph'][0]['sameAs']);
    }

    // The language the site is written in, which is what a request carrying no language of its own is served
    public function testTheWebSiteStatesTheWritingLanguage(): void
    {
        $snippet = $this->builder(['site-name' => 'Test Editions', 'site-url' => 'https://exemple.com'], null, 'en')->build();

        $this->assertSame('en', $snippet['@graph'][1]['inLanguage']);
    }

    // Read in another language, the node names that one: its description is the home page's summary, already read in it
    public function testTheWebSiteStatesTheLanguageItIsServedIn(): void
    {
        $snippet = $this->builder(['site-name' => 'Éditions Test', 'site-url' => 'https://exemple.com'])->build(null, 'A test site', 'en');

        $this->assertSame('en', $snippet['@graph'][1]['inLanguage']);
    }

    // A "</script>" typed into any setting must not close the tag the graph is printed in
    public function testTheEncodedGraphCannotCloseItsOwnScriptTag(): void
    {
        $json = $this->builder([
            'site-name' => 'Éditions </script><script>alert(1)</script>',
            'site-url' => 'https://exemple.com',
        ])->buildJson();

        $this->assertStringNotContainsString('</script>', $json);
        // The accented character stays readable rather than being escaped, the tag characters being the only ones taken out
        $this->assertStringContainsString('Éditions', $json);
    }

    // A stray byte pasted into a setting substitutes itself rather than turning the whole encoding into a "false" the string return type would 500 on
    public function testAnInvalidUtf8ByteStillYieldsAGraph(): void
    {
        $json = $this->builder([
            'site-name' => "\xB1\x31 Editions",
            'site-url' => 'https://exemple.com',
        ])->buildJson();

        $this->assertJson($json);
    }
}
