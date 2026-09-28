<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests;

use c975L\UiBundle\Service\JsonLdBuilder;
use c975L\UiBundle\Twig\JsonLdExtension;
use PHPUnit\Framework\TestCase;
use Twig\Environment;
use Twig\Extension\AttributeExtension;
use Twig\Loader\FilesystemLoader;
use Twig\RuntimeLoader\FactoryRuntimeLoader;
use Twig\TwigFilter;
use Twig\TwigFunction;

// Each tutorial film is published as the VideoObject a video result is drawn from, dated by the day it was shot
class TutorialVideoJsonLdTest extends TestCase
{
    public function testAFilmIsPublishedAsAVideoObject(): void
    {
        $html = $this->render();

        $this->assertSame(1, preg_match('#<script type="application/ld\+json">(.+?)</script>#s', $html, $matches), 'No VideoObject was published.');
        $video = json_decode($matches[1], true, 512, \JSON_THROW_ON_ERROR);

        $this->assertSame('VideoObject', $video['@type']);
        $this->assertSame('Créer une catégorie', $video['name']);
        $this->assertSame('https://example.test/medias/films/fr/book-category-creation.jpg?v=2', $video['thumbnailUrl']);
        $this->assertSame('https://example.test/medias/films/fr/book-category-creation.webm?v=2', $video['contentUrl']);
        $this->assertSame(date('c', 1790457662), $video['uploadDate']);
        $this->assertSame('https://example.test/tutoriels#book-category-creation', $video['url']);
    }

    private function render(): string
    {
        $twig = new Environment(new FilesystemLoader(\dirname(__DIR__) . '/templates'));
        $twig->addExtension(new AttributeExtension(JsonLdExtension::class));
        $twig->addRuntimeLoader(new FactoryRuntimeLoader([JsonLdExtension::class => static fn (): JsonLdExtension => new JsonLdExtension(new JsonLdBuilder())]));
        $twig->addFilter(new TwigFilter('trans', static fn (string $key): string => $key));
        $twig->addFunction(new TwigFunction('path', static fn (string $route): string => '/' . $route));
        $twig->addFunction(new TwigFunction('absolute_url', static fn (string $path): string => 'https://example.test' . $path));
        $twig->addGlobal('app', ['request' => ['baseUrl' => '', 'pathInfo' => '/tutoriels']]);

        return $twig->render('collection/TutorialItem.html.twig', [
            'tutorial' => [
                'slug' => 'book-category-creation',
                'label' => 'Créer une catégorie',
                'description' => 'Ranger ses livres.',
                'narrated' => true,
                'shotAt' => 1790457662,
                'video' => '/medias/films/fr/book-category-creation.webm?v=2',
                'subtitles' => '/medias/films/fr/book-category-creation.vtt?v=2',
                'poster' => '/medias/films/fr/book-category-creation.jpg?v=2',
                'locale' => 'fr',
                'steps' => [],
            ],
            'number' => 1,
            'previous' => null,
            'next' => null,
            'reportable' => false,
        ]);
    }
}
