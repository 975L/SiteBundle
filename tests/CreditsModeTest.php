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

// A footer credit is no longer a yes/no: it says what it shows - nothing, the logo, the name or both - which the two components read for themselves, being usable outside the footer
class CreditsModeTest extends TestCase
{
    // The four slugs that left this bundle for core-bundle with the modes (see UPGRADE.md), same group, same rows
    private const array MOVED_SLUGS = ['site-hosted-by-url', 'site-hosted-by-logo', 'display-made-by', 'display-hosted-by'];

    /**
     * @return array<string, array{string, string, string}>
     */
    public static function componentProvider(): array
    {
        return [
            'MadeBy' => ['MadeBy', 'display-made-by', 'madeBy'],
            'HostedBy' => ['HostedBy', 'display-hosted-by', 'hostedBy'],
        ];
    }

    #[\PHPUnit\Framework\Attributes\DataProvider('componentProvider')]
    public function testEachComponentReadsItsOwnMode(string $component, string $slug, string $prefix): void
    {
        $this->assertStringContainsString(
            sprintf("{%% set mode = credits_mode('%s') %%}", $slug),
            $this->component($component),
            sprintf('%s no longer reads its credits mode, so the four modes come down to a bool again.', $component)
        );
    }

    // A mode asking for a name nobody filled in would render the "Made by"/"Hosted by" label on its own, and one asking for a logo nobody set would render an empty image
    #[\PHPUnit\Framework\Attributes\DataProvider('componentProvider')]
    public function testEachPartStillNeedsTheConfigFeedingIt(string $component, string $slug, string $prefix): void
    {
        $template = $this->component($component);

        $this->assertStringContainsString(sprintf("{%% set showLogo = mode in ['logo', 'logo-name'] and %sLogo %%}", $prefix), $template);
        $this->assertStringContainsString(sprintf("{%% set showName = mode in ['name', 'logo-name'] and %sName %%}", $prefix), $template);
    }

    // The logo goes through Image:Link, which needs an url to hang on - a mode asking for both with no url falls through to the name alone
    #[\PHPUnit\Framework\Attributes\DataProvider('componentProvider')]
    public function testTheLogoBranchStillRequiresItsUrl(string $component, string $slug, string $prefix): void
    {
        $this->assertStringContainsString(
            sprintf('{%% if showLogo and %sUrl %%}', $prefix),
            $this->component($component)
        );
    }

    // Without an url the name must not render as an <a> at all, a href="" being a dead link that still looks clickable
    #[\PHPUnit\Framework\Attributes\DataProvider('componentProvider')]
    public function testTheNameRendersAsPlainTextWithoutAnUrl(string $component, string $slug, string $prefix): void
    {
        $this->assertStringContainsString(
            sprintf("{%% set tag = %sUrl ? 'a' : 'span' %%}", $prefix),
            $this->component($component),
            sprintf('%s renders the name as a link whatever the url, so a missing one leaves a dead <a>.', $component)
        );
    }

    // The footer only decides whether to draw the wrapper around the two, and a bool test on the config would read "logo-name" and "none" alike as true
    public function testTheFooterWrapperComparesTheModesToNone(): void
    {
        $footer = $this->component('Footer');

        $this->assertStringContainsString("{% set madeByMode = credits_mode('display-made-by') %}", $footer);
        $this->assertStringContainsString("{% set hostedByMode = credits_mode('display-hosted-by') %}", $footer);
        $this->assertStringContainsString("{% if madeByMode != 'none' or hostedByMode != 'none' %}", $footer);
    }

    // "Réalisé par" is false for a site that only runs the system rather than having been built by its maker, so the label comes from the wording config instead of being hardcoded
    public function testTheMadeByLabelComesFromTheWordingConfig(): void
    {
        $this->assertStringContainsString(
            "{% set label = made_by_label()|trans({}, 'site') ~",
            $this->component('MadeBy'),
            'MadeBy hardcodes its label again, so a site only running the system still reads "Made by".'
        );
    }

    // Both wordings the config offers must be translated in every locale the bundle ships
    public function testBothWordingLabelsAreTranslatedEverywhere(): void
    {
        foreach (['fr', 'en', 'es'] as $locale) {
            $translations = (string) file_get_contents(\dirname(__DIR__) . '/translations/site.' . $locale . '.xlf');

            foreach (['label.made_by', 'label.powered_by'] as $key) {
                $this->assertStringContainsString('<source>' . $key . '</source>', $translations, sprintf('"%s" is missing from the %s translations.', $key, $locale));
            }
        }
    }

    // Declaring them here too would hand core-bundle a second, competing definition of the very same site_config rows
    public function testTheMovedConfigsAreNoLongerDeclaredByThisBundle(): void
    {
        $configs = json_decode((string) file_get_contents(\dirname(__DIR__) . '/config/configs.json'), true, 512, \JSON_THROW_ON_ERROR);
        $slugs = array_column($configs, 'slug');

        foreach (self::MOVED_SLUGS as $slug) {
            $this->assertNotContains($slug, $slugs, sprintf('"%s" belongs to core-bundle now and must not be declared twice.', $slug));
        }
    }

    private function component(string $name): string
    {
        $path = \dirname(__DIR__) . '/templates/components/General/' . $name . '.html.twig';
        $this->assertFileExists($path);

        return (string) file_get_contents($path);
    }
}
