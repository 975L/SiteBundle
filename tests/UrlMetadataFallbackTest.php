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
use Twig\Environment;
use Twig\Loader\ArrayLoader;
use Twig\TwigFunction;

// What an url says of itself only fills a silence: an entity always speaks first, and the order the layout resolves the three in is the whole behaviour. The preamble is read out of layout.html.twig itself rather than restated here, so this checks the template that actually ships
class UrlMetadataFallbackTest extends TestCase
{
    public function testTheRowStatesTheTitleNoEntityCarries(): void
    {
        $rendered = $this->render($this->row(title: 'Every book'), []);

        $this->assertSame('Every book', $rendered['title']);
    }

    // A Page states its own from its columns before the layout runs, and the row must not take it back
    public function testAnEntityTitleWins(): void
    {
        $rendered = $this->render($this->row(title: 'Every book'), ['title' => 'The Guild']);

        $this->assertSame('The Guild', $rendered['title']);
    }

    public function testTheRowStatesTheSummaryNoEntityCarries(): void
    {
        $rendered = $this->render($this->row(summary: 'All the books published.'), []);

        $this->assertSame('All the books published.', $rendered['summary']);
    }

    public function testAnEntitySummaryWins(): void
    {
        $rendered = $this->render($this->row(summary: 'All the books published.'), ['summarySocialNetwork' => 'A knight rides.']);

        $this->assertSame('A knight rides.', $rendered['summary']);
    }

    // The share image is resolved here and not by the template rendering the Page, so the row comes after it rather than before
    public function testAPageShareImageWinsOverTheRow(): void
    {
        $rendered = $this->render($this->row(ogImage: 'listing.jpg'), ['page' => $this->page('page.jpg')]);

        $this->assertStringStartsWith('/page.jpg', $rendered['ogImage']);
    }

    public function testTheRowShareImageWinsOverTheSiteDefault(): void
    {
        $rendered = $this->render($this->row(ogImage: 'listing.jpg'), [], 'default.jpg');

        $this->assertStringStartsWith('/listing.jpg', $rendered['ogImage']);
    }

    // The row carries no image of its own, and the site's default takes over as it did before
    public function testTheSiteDefaultTakesOverWhenTheRowCarriesNoImage(): void
    {
        $rendered = $this->render($this->row(title: 'Every book'), [], 'default.jpg');

        $this->assertStringStartsWith('/default.jpg', $rendered['ogImage']);
    }

    // The logo is the last word, and it is read from the media library like the three before it rather than from a "logo" variable the layout used to expect
    public function testTheSiteLogoIsTheLastResort(): void
    {
        $rendered = $this->render(null, [], siteLogo: 'logo.png');

        $this->assertStringStartsWith('/logo.png', $rendered['ogImage']);
    }

    public function testTheSiteDefaultWinsOverTheLogo(): void
    {
        $rendered = $this->render(null, [], 'default.jpg', 'logo.png');

        $this->assertStringStartsWith('/default.jpg', $rendered['ogImage']);
    }

    public function testNoRowLeavesEverythingUnsaid(): void
    {
        $rendered = $this->render(null, []);

        $this->assertSame('', $rendered['title']);
        $this->assertSame('', $rendered['summary']);
        $this->assertSame('', $rendered['ogImage']);
    }

    // The layout's own preamble, rendered on its own - the surrounding layout pulls in the whole application (assets, nonces, config), which this behaviour doesn't depend on
    private function render(?object $urlMetadata, array $context, ?string $siteOgImage = null, ?string $siteLogo = null): array
    {
        $layout = (string) file_get_contents(dirname(__DIR__) . '/templates/layout.html.twig');

        $this->assertSame(
            1,
            preg_match('/(\{% set urlMetadata = url_metadata\(\) %\}.*?)\{# pageTitle #\}/s', $layout, $matches),
            'The url metadata preamble is no longer written between "url_metadata()" and the "pageTitle" comment in layout.html.twig, this test can no longer read it.'
        );

        $source = $matches[1] . "{{ title|default('') }}|{{ summarySocialNetwork|default('') }}|{{ ogImage|default('') }}";

        $twig = new Environment(new ArrayLoader(['preamble' => $source]));
        $twig->addFunction(new TwigFunction('url_metadata', static fn (): ?object => $urlMetadata));
        $siteMedia = ['og-image' => $siteOgImage, 'logo' => $siteLogo];
        $twig->addFunction(new TwigFunction('site_media', static fn (string $role): ?object => null === ($siteMedia[$role] ?? null) ? null : self::media($siteMedia[$role])));
        $twig->addFunction(new TwigFunction('asset', static fn (string $path): string => $path));
        $twig->addFunction(new TwigFunction('absolute_url', static fn (string $path): string => $path));

        $rendered = array_map(trim(...), explode('|', $twig->render('preamble', $context)));

        return ['title' => $rendered[0], 'summary' => $rendered[1], 'ogImage' => $rendered[2]];
    }

    private function row(?string $title = null, ?string $summary = null, ?string $ogImage = null): object
    {
        return new readonly class ($title, $summary, null === $ogImage ? null : self::media($ogImage)) {
            public function __construct(
                public ?string $title,
                public ?string $summarySocialNetwork,
                public ?object $ogImage,
            ) {
            }
        };
    }

    private function page(string $filename): object
    {
        return new readonly class (self::media($filename)) {
            public function __construct(public object $ogImage)
            {
            }
        };
    }

    private static function media(string $filename): object
    {
        return new readonly class ($filename) {
            public function __construct(public string $filename)
            {
            }

            public function getUpdatedAt(): \DateTimeImmutable
            {
                return new \DateTimeImmutable('2026-08-14 12:00:00');
            }
        };
    }
}
