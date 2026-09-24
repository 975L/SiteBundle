<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\ConfigBundle\Service\SiteLocales;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PageLinkLocalizer;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\PageTranslator;
use c975L\SiteBundle\Twig\MenuExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;

class PageLinkLocalizerTest extends TestCase
{
    // The whole point: a visitor reading the site in English follows a card into English, never back into the language they left
    public function testAPageLinkIsReadInTheLanguageThePageAroundItIsReadIn(): void
    {
        $this->assertSame('/en/pages/nos-ateliers', $this->localizer('en')->localize('/pages/nos-ateliers'));
    }

    // A word linked inside a rich text is a link like any other; the same words elsewhere in the prose are prose
    public function testTheLinksOfARichTextAreRewrittenAndItsProseIsNot(): void
    {
        $this->assertSame(
            'Voir <a href="/en/pages/nos-ateliers">nos ateliers</a>, pas /pages/nos-ateliers en toutes lettres',
            $this->localizer('en')->localize('Voir <a href="/pages/nos-ateliers">nos ateliers</a>, pas /pages/nos-ateliers en toutes lettres'),
        );
    }

    // A query string and an anchor travel with the link rather than being dropped
    public function testWhatFollowsTheSlugTravelsWithTheLink(): void
    {
        $this->assertSame('/en/pages/nos-ateliers#tarifs', $this->localizer('en')->localize('/pages/nos-ateliers#tarifs'));
    }

    // A page url carries sub-navigation of its own ("/pages/blocks/Site", which PageController answers 200 for): the first segment is the slug, everything after it travels along
    public function testASubSegmentTravelsWithTheLinkAndTheSlugStaysTheFirstOne(): void
    {
        $this->assertSame('/en/pages/nos-ateliers/Site#block-a', $this->localizer('en')->localize('/pages/nos-ateliers/Site#block-a'));
    }

    // The home page answers at the site root: "/en/pages/home" would only ever 301 there, costing every visitor of another language a hop
    public function testALinkToTheHomePageGoesStraightToTheSiteRoot(): void
    {
        $this->assertSame('/en/', $this->localizer('en', 'home')->localize('/pages/home'));
    }

    // The no-regression contract: the language the site is written in keeps the urls it always had
    public function testTheWritingLanguageIsLeftExactlyAsItWas(): void
    {
        $this->assertSame('/pages/nos-ateliers', $this->localizer(null)->localize('/pages/nos-ateliers'));
    }

    // A localised url answers for nothing there (PageController 404s it), so an untranslated page keeps the writing language's link rather than one the visitor lands on a 404 from
    public function testAPageThatSaysNothingInThatLanguageKeepsItsOwnLink(): void
    {
        $this->assertSame('/pages/nos-ateliers', $this->localizer('es')->localize('/pages/nos-ateliers'));
    }

    // Everything that is not this site's own page link: an external url, a mailto, a bare anchor, another bundle's path
    public function testAnythingElseIsGivenBackUntouched(): void
    {
        $localizer = $this->localizer('en');

        foreach (['https://example.com/pages/x', 'mailto:a@b.c', '#tarifs', '/shop/panier', '/pages/', ''] as $value) {
            $this->assertSame($value, $localizer->localize($value));
        }
    }

    // A rich text is re-rendered on every request when its block declares itself uncacheable, so a page linked twice used to cost two queries - findOneBy() on a slug going back to base every time, the identity map only answering for an identifier
    public function testThePageBehindASlugIsLookedUpOnceForAWholeText(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects($this->once())->method('findOneBy')->willReturn(new Page()->setTitle('Nos ateliers')->setSlug('nos-ateliers'));

        $text = '<a href="/pages/nos-ateliers">un</a> <a href="/pages/nos-ateliers">deux</a>';

        $this->assertSame(
            '<a href="/en/pages/nos-ateliers">un</a> <a href="/en/pages/nos-ateliers">deux</a>',
            $this->localizer('en', repository: $repository)->localize($text)
        );
    }

    // And a slug naming no page is remembered as naming none, or the search would be run again at every occurrence of it
    public function testASlugNamingNoPageIsSearchedForOnce(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects($this->once())->method('findOneBy')->willReturn(null);

        $text = '<a href="/pages/inconnue">un</a> <a href="/pages/inconnue">deux</a>';

        $this->assertSame($text, $this->localizer('en', repository: $repository)->localize($text));
    }

    // A target picked in a block's link field is the value a menu link stores, read back the same way - in the writing language too, where nothing else here is rewritten
    public function testATargetPickedInALinkFieldIsTurnedIntoItsUrlInEveryLanguage(): void
    {
        $menu = $this->createStub(MenuExtension::class);
        $menu->method('getMenuLinkUrl')->willReturnMap([['page:53#services-75', '/pages/publier-votre-histoire#services-75']]);

        $this->assertSame(
            '<a href="/pages/publier-votre-histoire#services-75" role="button">Nos services</a>',
            $this->localizer(null, menuExtension: $menu)->localize('<a href="page:53#services-75" role="button">Nos services</a>'),
        );
    }

    // A url of its own is left alone in the writing language, however many "page" or "route" words the prose around it holds
    public function testAnAddressTypedByHandIsNotATarget(): void
    {
        $menu = $this->createMock(MenuExtension::class);
        $menu->expects($this->never())->method('getMenuLinkUrl');

        $text = 'Une page: <a href="/shop">la boutique</a>, une route: <a href="https://route.example">ailleurs</a>';

        $this->assertSame($text, $this->localizer(null, menuExtension: $menu)->localize($text));
    }

    private function localizer(?string $readingLocale, string $slug = 'nos-ateliers', ?PageRepository $repository = null, ?MenuExtension $menuExtension = null): PageLinkLocalizer
    {
        $request = Request::create('/');
        if (null !== $readingLocale) {
            $request->attributes->set('_locale', $readingLocale);
        }

        // The real resolver's own rule, in two lines: the home page answers at the site root, every other page under its slug
        $resolver = $this->createStub(PagePublicUrlResolver::class);
        $resolver->method('resolvePath')->willReturnCallback(
            static fn (Page $page, ?string $locale = null): string => 'home' === $page->getSlug()
                ? sprintf('/%s/', $locale)
                : sprintf('/%s/pages/%s', $locale, $page->getSlug())
        );

        if (null === $repository) {
            $page = new Page()->setTitle('Nos ateliers')->setSlug($slug);
            $repository = $this->createStub(PageRepository::class);
            $repository->method('findOneBy')->willReturn($page);
        }

        // Written in French and in English, and in nothing else
        $translator = $this->createStub(PageTranslator::class);
        $translator->method('translatedLocales')->willReturn(['fr', 'en']);

        return new PageLinkLocalizer($resolver, new SiteLocales(['fr', 'en', 'es'], 'fr'), $translator, $repository, new RequestStack([$request]), $menuExtension);
    }
}
