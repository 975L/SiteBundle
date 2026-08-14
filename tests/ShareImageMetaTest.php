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

// What the layout says of the share image besides its url - its room and what it shows. Both are stated only where they are known, a guess being worse than a silence here. The block is read out of layout.html.twig itself rather than restated here, so this checks the template that actually ships
class ShareImageMetaTest extends TestCase
{
    public function testTheImageUrlIsStatedOnItsOwnWhenNoMediaAnsweredForIt(): void
    {
        $rendered = $this->renderImageBlock(['ogImage' => 'https://example.com/og.jpg']);

        $this->assertStringContainsString('<meta property="og:image" content="https://example.com/og.jpg">', $rendered);
        $this->assertStringNotContainsString('og:image:width', $rendered);
        $this->assertStringNotContainsString('og:image:alt', $rendered);
    }

    public function testNothingIsStatedWhenThereIsNoImage(): void
    {
        $this->assertSame('', trim($this->renderImageBlock([])));
    }

    public function testTheMediaDimensionsKeepTheThumbnailsRoom(): void
    {
        $rendered = $this->renderImageBlock([
            'ogImage' => 'https://example.com/og.jpg',
            'ogImageMedia' => $this->media(width: '1200', height: '630'),
        ]);

        $this->assertStringContainsString('<meta property="og:image:width" content="1200">', $rendered);
        $this->assertStringContainsString('<meta property="og:image:height" content="630">', $rendered);
    }

    // The media library's own columns are admin-typed display values, where Open Graph only reads a bare pixel count
    public function testACssLengthIsNotOfferedAsADimension(): void
    {
        foreach ([['50%', '50%'], ['auto', 'auto'], ['100px', '80px'], ['1200', 'auto']] as [$width, $height]) {
            $rendered = $this->renderImageBlock([
                'ogImage' => 'https://example.com/og.jpg',
                'ogImageMedia' => $this->media(width: $width, height: $height),
            ]);

            $this->assertStringNotContainsString(
                'og:image:width',
                $rendered,
                sprintf('A media measured "%s" by "%s" is offered as a pixel count.', $width, $height)
            );
        }
    }

    public function testTheMediaAlternativeTextSaysWhatTheImageShows(): void
    {
        $rendered = $this->renderImageBlock([
            'ogImage' => 'https://example.com/og.jpg',
            'ogImageMedia' => $this->media(alt: 'A knight riding through the mist'),
        ]);

        $this->assertStringContainsString('<meta property="og:image:alt" content="A knight riding through the mist">', $rendered);
    }

    // A template setting the image itself is the only one that knows what it shows, and says it under this name
    public function testATemplatesOwnAlternativeTextWins(): void
    {
        $rendered = $this->renderImageBlock([
            'ogImage' => 'https://example.com/og.jpg',
            'ogImageMedia' => $this->media(alt: 'The media library says this'),
            'ogImageAlt' => 'The template says that',
        ]);

        $this->assertStringContainsString('<meta property="og:image:alt" content="The template says that">', $rendered);
        $this->assertStringNotContainsString('The media library says this', $rendered);
    }

    public function testAnEmptyAlternativeTextIsNotStated(): void
    {
        $rendered = $this->renderImageBlock([
            'ogImage' => 'https://example.com/og.jpg',
            'ogImageMedia' => $this->media(alt: null),
        ]);

        $this->assertStringNotContainsString('og:image:alt', $rendered);
    }

    // The layout's own image block, rendered on its own - the surrounding layout pulls in the whole application (assets, nonces, config), which this behaviour doesn't depend on
    private function renderImageBlock(array $context): string
    {
        $layout = (string) file_get_contents(dirname(__DIR__) . '/templates/layout.html.twig');

        $this->assertSame(
            1,
            preg_match('/\{# Image #\}(.*?)\{# Url #\}/s', $layout, $matches),
            'The share image block is no longer written between the "Image" and the "Url" comments in layout.html.twig, this test can no longer read it.'
        );

        $twig = new Environment(new ArrayLoader(['image' => $matches[1]]));

        return $twig->render('image', $context);
    }

    private function media(?string $width = null, ?string $height = null, ?string $alt = null): object
    {
        return new readonly class ($width, $height, $alt) {
            public function __construct(
                private ?string $width,
                private ?string $height,
                private ?string $alt,
            ) {
            }

            public function getIntrinsicWidth(): ?int
            {
                return ctype_digit((string) $this->width) ? (int) $this->width : null;
            }

            public function getIntrinsicHeight(): ?int
            {
                return ctype_digit((string) $this->height) ? (int) $this->height : null;
            }

            public function getAlt(): ?string
            {
                return $this->alt;
            }
        };
    }
}
