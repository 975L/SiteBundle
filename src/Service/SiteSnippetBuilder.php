<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\UiBundle\Registry\SameAsRegistry;

// Builds the schema.org graph the home page publishes: who publishes the site (Organization) and what the site itself is (WebSite).
// Every bundle already describes its own entities - a book, a product, a photo - and none of them says who stands behind them. This is that missing node, and it is what the "sameAs" profiles hang from: it ties the site, its social accounts and its catalog into a single entity rather than a scattering of unrelated pages.
// Built from the site's own configuration, so a site publishes it by having filled its back-office rather than by writing any template.
class SiteSnippetBuilder
{
    // What the site says it is, picked in the back-office (config "site-schema-type"): a publishing house is an Organization, a personal site or a CV is the Person it is about, an agency selling a service is a ProfessionalService, and a business with an address a LocalBusiness. They are not interchangeable - publishing an agency as a Person, or someone's CV as an Organization, is the kind of claim a search engine builds a knowledge panel on. All but Person are Organization subtypes, so they carry the same properties below
    public const array TYPES = ['Organization', 'Person', 'ProfessionalService', 'LocalBusiness'];

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly SiteLocales $siteLocales,
        private readonly SameAsRegistry $sameAsRegistry,
    ) {
    }

    /**
     * The two nodes as one "@graph", which is how schema.org states several entities of one page without repeating
     * the context - each carrying an "@id" so the WebSite names its publisher rather than describing it twice.
     *
     * Empty while the site has no name or no url: the first is what an Organization is identified by, the second what
     * both "@id" are built on, and a graph missing either indexes nothing.
     *
     * @param string|null $logoUrl     absolute, resolved by the caller - only a template turns a Media into a url
     * @param string|null $description the home page's own summary, which is already its meta description
     * @param string|null $locale      the language the page is being served in, the writing language when null
     *
     * @return array<string, mixed>
     */
    public function build(?string $logoUrl = null, ?string $description = null, ?string $locale = null): array
    {
        $siteName = trim((string) $this->configService->get('site-name'));
        $url = rtrim(trim((string) $this->configService->get('site-url')), '/');

        if ('' === $siteName || '' === $url) {
            return [];
        }

        $type = (string) $this->configService->get('site-schema-type');
        $type = \in_array($type, self::TYPES, true) ? $type : self::TYPES[0];

        return [
            '@context' => 'https://schema.org',
            '@graph' => [
                $this->publisher($type, $url, $siteName, trim((string) $logoUrl), trim((string) $description)),
                $this->website($type, $url, $siteName, trim((string) $description), $locale),
            ],
        ];
    }

    /**
     * Who stands behind the site, as the node every other one hangs its "@id" on.
     *
     * @return array<string, mixed>
     */
    private function publisher(string $type, string $url, string $siteName, string $image, string $description): array
    {
        $isPerson = 'Person' === $type;
        $author = trim((string) $this->configService->get('site-author'));

        return $this->clean([
            '@type' => $type,
            '@id' => self::publisherId($url, $type),
            // A personal site is about the person it names, so "site-author" is its subject and not somebody standing behind it - the site's own name is only the fallback, a CV often being titled with the name it is about anyway
            'name' => $isPerson && '' !== $author ? $author : $siteName,
            'url' => $url . '/',
            'description' => $description,
            // schema.org gives an Organization a "logo" and a Person an "image": the same file, under the property its own type reads
            'logo' => $isPerson ? '' : $image,
            'image' => $isPerson ? $image : '',
            'email' => trim((string) $this->configService->get('site-contact-email')),
            'telephone' => trim((string) $this->configService->get('site-contact-phone')),
            // Only where there is an organization for someone to have founded, and only when that someone is not the entity itself
            'founder' => $isPerson ? null : $this->founder($author, $siteName),
            // The profiles naming this same publisher elsewhere, contributed by whichever bundle owns them (see SameAsProviderInterface) - the property that tells a search engine the site and those accounts are one entity
            'sameAs' => $this->sameAsRegistry->all(),
        ]);
    }

    /**
     * What the site itself is, naming its publisher rather than describing it twice.
     *
     * @return array<string, mixed>
     */
    private function website(string $type, string $url, string $siteName, string $description, ?string $locale): array
    {
        return $this->clean([
            '@type' => 'WebSite',
            '@id' => $url . '/#website',
            'name' => $siteName,
            'url' => $url . '/',
            'description' => $description,
            // The language this very page is served in, not the ones the site declares: a WebSite node states one, and the description right above is already read in that language. A localised home page only ever answers for a language it was written in (see PageController::requireTranslated()), so this never names one nothing was translated into
            'inLanguage' => null !== $locale && '' !== $locale ? $locale : $this->siteLocales->getDefaultLocale(),
            'publisher' => ['@id' => self::publisherId($url, $type)],
        ]);
    }

    // The publisher's own "@id", built in one place so the WebSite node can never name one the graph does not carry
    private static function publisherId(string $url, string $type): string
    {
        return $url . '/#' . ('Person' === $type ? 'person' : 'organization');
    }

    // The same graph, encoded for a <script type="application/ld+json">; empty string when there is nothing to publish
    public function buildJson(?string $logoUrl = null, ?string $description = null, ?string $locale = null): string
    {
        $snippet = $this->build($logoUrl, $description, $locale);

        if ([] === $snippet) {
            return '';
        }

        // JSON_HEX_TAG keeps a "</script>" typed into a field from closing the tag, JSON_INVALID_UTF8_SUBSTITUTE keeps a stray byte from emptying the whole graph
        return json_encode($snippet, \JSON_HEX_TAG | \JSON_HEX_AMP | \JSON_HEX_APOS | \JSON_HEX_QUOT | \JSON_UNESCAPED_SLASHES | \JSON_UNESCAPED_UNICODE | \JSON_INVALID_UTF8_SUBSTITUTE);
    }

    /**
     * The person behind the organization, when one is named and is somebody other than the organization itself - a node
     * rather than a plain string, so a search engine reads a person and not a label.
     *
     * Null when "site-author" repeats the site's own name, which is how a one-person structure fills it: an Organization
     * founded by an identically named Person is a claim nobody meant to make.
     *
     * @return array<string, string>|null
     */
    private function founder(string $author, string $siteName): ?array
    {
        return '' === $author || $author === $siteName ? null : ['@type' => 'Person', 'name' => $author];
    }

    /**
     * @param array<string, mixed> $snippet
     *
     * @return array<string, mixed>
     */
    private function clean(array $snippet): array
    {
        return array_filter($snippet, static fn ($value) => !\in_array($value, ['', [], null, 0], true));
    }
}
