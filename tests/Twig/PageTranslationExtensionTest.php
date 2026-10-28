<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Twig;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\PageTranslator;
use c975L\SiteBundle\Twig\PageTranslationExtension;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Service\ContentTranslator;
use c975L\UiBundle\Service\MediaTranslator;
use PHPUnit\Framework\TestCase;

class PageTranslationExtensionTest extends TestCase
{
    private function createPage(): Page
    {
        return new Page()->setSlug('ateliers')->setTitle('Nos ateliers')->setSummarySocialNetwork('Un atelier par mois');
    }

    /**
     * @param array<string, string> $alternates
     * @param list<string>          $translatedLocales
     */
    private function createExtension(string $title = 'Nos ateliers', ?string $summary = 'Un atelier par mois', array $alternates = [], array $translatedLocales = ['fr'], string $path = '/pages/ateliers', array $mediaTranslation = []): PageTranslationExtension
    {
        $pageTranslator = $this->createStub(PageTranslator::class);
        $pageTranslator->method('getTitle')->willReturn($title);
        $pageTranslator->method('getSummarySocialNetwork')->willReturn($summary);
        $pageTranslator->method('translatedLocales')->willReturn($translatedLocales);

        $pagePublicUrlResolver = $this->createStub(PagePublicUrlResolver::class);
        $pagePublicUrlResolver->method('resolveAlternates')->willReturn($alternates);
        $pagePublicUrlResolver->method('resolvePath')->willReturn($path);

        // A real MediaTranslator over a stubbed table: the overlay it lays on the media is what is under test
        $contentTranslator = $this->createStub(ContentTranslator::class);
        $contentTranslator->method('isActive')->willReturn(true);
        $contentTranslator->method('translate')->willReturn($mediaTranslation);

        return new PageTranslationExtension($pageTranslator, $pagePublicUrlResolver, new MediaTranslator($contentTranslator));
    }

    // A share image as read back from the database, its id set the way Doctrine sets it
    private function createOgImage(): Media
    {
        $media = new Media()->setFilename('medias/site/media-ab.webp')->setAlt('Nos ateliers');
        new \ReflectionProperty(Media::class, 'id')->setValue($media, 7);

        return $media;
    }

    // The title a template reads is the one of the language being served, never the raw column
    public function testTheTitleIsTheTranslatedOne(): void
    {
        $this->assertSame('Our workshops', $this->createExtension('Our workshops')->getTitle($this->createPage()));
    }

    // A site declaring a single language reads back exactly what the page holds
    public function testTheTitleIsThePagesOwnWhenNothingIsTranslated(): void
    {
        $this->assertSame('Nos ateliers', $this->createExtension()->getTitle($this->createPage()));
    }

    public function testTheSummaryIsTheTranslatedOne(): void
    {
        $this->assertSame('One a month', $this->createExtension(summary: 'One a month')->getSummary($this->createPage()));
    }

    // A page carrying no summary at all says nothing rather than an empty string, so the layout falls back on its own sources
    public function testTheSummaryIsNullWhenThePageCarriesNone(): void
    {
        $this->assertNull($this->createExtension(summary: null)->getSummary($this->createPage()));
    }

    // What the layout writes its "hreflang" links from, the page naming itself in every declared language
    public function testTheAlternatesAreTheResolversOwn(): void
    {
        $alternates = ['fr' => 'https://exemple.com/pages/ateliers', 'en' => 'https://exemple.com/en/pages/ateliers'];

        $this->assertSame($alternates, $this->createExtension(alternates: $alternates)->getAlternates($this->createPage()));
    }

    // Nothing is emitted on a site declaring a single language: the layout skips the tags altogether
    public function testThereAreNoAlternatesOnASiteDeclaringOneLanguage(): void
    {
        $this->assertSame([], $this->createExtension()->getAlternates($this->createPage()));
    }

    // The language menu points at the bare url with "?_locale=xx", never at "/en/..." : a localised route says on its own which language it answers in, and ConfigBundle's LocaleListener leaves the session alone there - the choice would last exactly one page
    public function testTheLanguagesAreSwitchedThroughTheBareUrl(): void
    {
        $languages = $this->createExtension(translatedLocales: ['fr', 'en'])->getLanguages($this->createPage());

        $this->assertSame([
            'fr' => '/pages/ateliers?_locale=fr',
            'en' => '/pages/ateliers?_locale=en',
        ], $languages);
    }

    // A page written in one language alone offers no menu: there is nothing to choose between, and its other urls answer 404
    public function testThereAreNoLanguagesOnAPageNobodyTranslated(): void
    {
        $this->assertSame([], $this->createExtension()->getLanguages($this->createPage()));
    }

    // A language given a picture of its own shares that one, with its own alternative text
    public function testTheOgImageIsTheLanguagesOwnFile(): void
    {
        $page = $this->createPage()->setOgImage($this->createOgImage());

        $ogImage = $this->createExtension(mediaTranslation: ['filename' => 'medias/site/media-ab-en-1a2b3c4d.webp', 'alt' => 'Our workshops'])->getOgImage($page);

        $this->assertSame('medias/site/media-ab-en-1a2b3c4d.webp', $ogImage?->getFilename());
        $this->assertSame('Our workshops', $ogImage->getAlt());
    }

    // A language given nothing shares the image the page was written with
    public function testTheOgImageIsThePagesOwnWhenNothingIsTranslated(): void
    {
        $page = $this->createPage()->setOgImage($this->createOgImage());

        $this->assertSame('medias/site/media-ab.webp', $this->createExtension()->getOgImage($page)?->getFilename());
    }

    // A page without a share image leaves the layout's cascade to the site's default
    public function testThereIsNoOgImageWhenThePageCarriesNone(): void
    {
        $this->assertNull($this->createExtension()->getOgImage($this->createPage()));
    }
}
