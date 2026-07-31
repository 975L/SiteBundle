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
use c975L\SiteBundle\Service\LegalModelCatalog;
use c975L\SiteBundle\Service\LegalModelPlaceholders;
use c975L\SiteBundle\Service\LegalModelRenderer;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Extra\Intl\IntlExtension;
use Twig\Loader\FilesystemLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

// Guards the shipped templates themselves rather than the renderer: the customization delta is keyed on
// "data-legal-id", so an untagged section is silently uncustomizable, a duplicated identifier customizes two
// places at once, and identifiers drifting apart between locales lose a client's work the day they add a
// language
class LegalModelTemplatesTest extends TestCase
{
    private const LOCALES = ['fr', 'en', 'es'];

    // Shared, so the legal_var() binding below and the renderer switch marker mode on the same instance
    private ?LegalModelPlaceholders $placeholders = null;

    public static function models(): iterable
    {
        foreach ((new LegalModelCatalog())->all() as $model) {
            yield $model => [$model];
        }
    }

    #[DataProvider('models')]
    public function testEverySectionAndSubSectionCarriesAnIdentifier(string $model): void
    {
        foreach (self::LOCALES as $locale) {
            $html = self::read($model, $locale);

            $this->assertSame(
                substr_count($html, '<section'),
                preg_match_all('/<section data-legal-id="[^"]+"/', $html),
                sprintf('%s.%s: a <section> carries no data-legal-id', $model, $locale),
            );
            $this->assertSame(
                substr_count($html, '<h3'),
                preg_match_all('/<h3 data-legal-id="[^"]+"/', $html),
                sprintf('%s.%s: an <h3> carries no data-legal-id', $model, $locale),
            );
        }
    }

    #[DataProvider('models')]
    public function testIdentifiersAreUniqueWithinAModel(string $model): void
    {
        foreach (self::LOCALES as $locale) {
            $ids = self::identifiers($model, $locale);

            $this->assertSame(
                array_values(array_unique($ids)),
                $ids,
                sprintf('%s.%s: duplicate data-legal-id', $model, $locale),
            );
        }
    }

    // A delta stored while browsing in French must still apply when the same page is served in Spanish
    #[DataProvider('models')]
    public function testIdentifiersAreIdenticalAcrossLocales(string $model): void
    {
        $reference = self::identifiers($model, LegalModelCatalog::FALLBACK_LOCALE);

        foreach (self::LOCALES as $locale) {
            $this->assertSame($reference, self::identifiers($model, $locale), sprintf('%s.%s diverges', $model, $locale));
        }
    }

    // Sub-sections are dotted under the section they belong to, which is what scopes a hide or a reorder
    #[DataProvider('models')]
    public function testSubSectionIdentifiersAreNestedUnderAnExistingSection(string $model): void
    {
        $ids = self::identifiers($model, LegalModelCatalog::FALLBACK_LOCALE);
        $parents = array_map(
            static fn (string $id): string => substr($id, 0, (int) strpos($id, '.')),
            array_filter($ids, static fn (string $id): bool => str_contains($id, '.')),
        );

        $this->assertSame(
            [],
            array_values(array_diff($parents, $ids)),
            sprintf('%s: sub-section nested under a section that does not exist', $model),
        );
    }

    // The site's identity has to stay a %marker%, otherwise a rewritten section freezes whatever the site was
    // called that day - config() is only left where it drives an {% if %}, never where it prints identity
    #[DataProvider('models')]
    public function testIdentityValuesAreEmittedAsPlaceholders(string $model): void
    {
        foreach (self::LOCALES as $locale) {
            preg_match_all("/\{\{ config\('([a-z0-9-]+)'\)/", self::read($model, $locale), $matches);

            $this->assertSame(
                [],
                array_values(array_diff($matches[1], ['site-other-cookies', 'site-other-copyright'])),
                sprintf('%s.%s prints a config() value that should go through legal_var()', $model, $locale),
            );
        }
    }

    // End to end on the real templates, not a stand-in: every shipped model must parse into the units the
    // customization screen lists, and a delta must apply to them
    #[DataProvider('models')]
    public function testShippedModelsRenderAndCustomize(string $model): void
    {
        $renderer = $this->renderer();

        foreach (self::LOCALES as $locale) {
            $units = $renderer->units($model, $locale);
            $ids = array_column($units, 'id');

            // A subset, not an equality: the sections wrapped in {% if config(...) %} (other cookies, other
            // copyrights, owner) only exist once the site filled that value in, and nothing is set here
            $this->assertSame(
                array_values(array_intersect(self::identifiers($model, $locale), $ids)),
                $ids,
                sprintf('%s.%s: units do not match the template order', $model, $locale),
            );

            $first = $units[0]['id'];
            $html = $renderer->render($model, '2026-01-01', [
                'hidden' => [$first],
                'overrides' => [$units[1]['id'] => ['content' => '<p>Ours</p>']],
            ], $locale);

            $this->assertStringNotContainsString(sprintf('data-legal-id="%s"', $first), $html);
            $this->assertStringContainsString('<p>Ours</p>', $html);
            $this->assertStringNotContainsString('%site-name%', $html);
        }
    }

    // The other documented way to put a model on a page: a plain {% include %} from an app's own template,
    // with no renderer and therefore no post-processing. It must read as finished text, not as %site-name%
    #[DataProvider('models')]
    public function testAModelIncludedDirectlyPrintsNoMarker(string $model): void
    {
        $html = $this->twig()->render(
            sprintf('@c975LSite/models/%s.%s.html.twig', $model, LegalModelCatalog::FALLBACK_LOCALE),
            ['latestUpdate' => '2026-01-01'],
        );

        $this->assertStringNotContainsString('%site-', $html, sprintf('%s leaks a marker when included directly', $model));

        // The cookies policy is the one model that never names the site, so only the others can be checked
        // for the resolved value itself
        if (str_contains(self::read($model, LegalModelCatalog::FALLBACK_LOCALE), "legal_var('site-name')")) {
            $this->assertStringContainsString('Acme', $html);
        }
    }

    private function renderer(): LegalModelRenderer
    {
        return new LegalModelRenderer($this->twig(), $this->placeholders(), new LegalModelCatalog());
    }

    // The models' own Twig environment, with the handful of things they call that come from the app stubbed
    // down to what the markup needs
    private function twig(): Environment
    {
        $placeholders = $this->placeholders();

        $loader = new FilesystemLoader();
        $loader->addPath(\dirname(__DIR__, 2) . '/templates', 'c975LSite');

        $twig = new Environment($loader);
        $twig->addExtension(new IntlExtension());
        $twig->addFunction(new TwigFunction('legal_var', $placeholders->value(...), ['is_safe' => ['html']]));
        $twig->addFunction(new TwigFunction('config', static fn (): ?string => null));
        $twig->addFunction(new TwigFunction('site_legal_pages', static fn (): array => []));
        $twig->addFunction(new TwigFunction('path', static fn (): string => '/'));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));

        return $twig;
    }

    private function placeholders(): LegalModelPlaceholders
    {
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturnCallback(
            static fn (string $slug): ?string => 'site-name' === $slug ? 'Acme' : null
        );

        return $this->placeholders ??= new LegalModelPlaceholders($config);
    }

    private static function read(string $model, string $locale): string
    {
        return (string) file_get_contents(sprintf('%s/templates/models/%s.%s.html.twig', \dirname(__DIR__, 2), $model, $locale));
    }

    private static function identifiers(string $model, string $locale): array
    {
        preg_match_all('/data-legal-id="([^"]+)"/', self::read($model, $locale), $matches);

        return $matches[1];
    }
}
