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
use Twig\TwigFilter;
use Twig\TwigFunction;

// The language menu is markup on one url and silence on every other: a component rendered without a Page, or under a url the Page's languages do not name, must offer nothing at all - and nothing else in the suite would show that as a failure
class NavbarLanguagesTest extends TestCase
{
    private function template(string $name): string
    {
        return (string) file_get_contents(dirname(__DIR__) . '/templates/' . $name);
    }

    // The component as it ships, rendered against stubs of the three things it reads outside itself
    private function renderLanguages(array $context, array $languages = []): string
    {
        $twig = new Environment(new ArrayLoader(['languages' => $this->template('components/General/Languages.html.twig')]));
        $twig->addFunction(new TwigFunction('page_languages', static fn (mixed $page): array => $languages));
        $twig->addFilter(new TwigFilter('trans', static fn (string $id): string => $id));
        $twig->addFilter(new TwigFilter('locale_name', static fn (string $locale): string => strtoupper($locale)));

        return $twig->render('languages', $context + ['app' => ['request' => ['locale' => 'fr']]]);
    }

    // Both bars: the one built out of menu blocks and the fallback a site with none is served
    public function testTheNavbarRendersTheLanguagesOnBothBars(): void
    {
        $this->assertSame(2, substr_count($this->template('components/General/Navbar.html.twig'), '<twig:c975LSite:General:Languages'));
    }

    // The layout reads a variable a template declares rather than the Page itself, which is what lets a screen showing something else under a Page's url stay out of the menu
    public function testTheLayoutHandsTheNavbarPageOverToTheNavbar(): void
    {
        $this->assertStringContainsString('<twig:c975LSite:General:Navbar page="{{ navbarPage|default(null) }}" />', $this->template('layout.html.twig'));
    }

    public function testAPageWrittenInSeveralLanguagesOffersThem(): void
    {
        $rendered = $this->renderLanguages(['page' => 'a-page'], ['fr' => '/pages/contact?_locale=fr', 'en' => '/pages/contact?_locale=en']);

        $this->assertStringContainsString('href="/pages/contact?_locale=en"', $rendered);
        $this->assertStringContainsString('hreflang="en"', $rendered);
    }

    // Every screen with no Page behind it - a book, a product, a photo - and the two the layout hands a null for
    public function testNoPageRendersNothingAtAll(): void
    {
        $this->assertSame('', trim($this->renderLanguages(['page' => null])));
        $this->assertSame('', trim($this->renderLanguages([])));
    }

    // A page nobody translated: page_languages() answers an empty list, and a bar with a single choice is no choice
    public function testAPageWrittenInASingleLanguageRendersNothing(): void
    {
        $this->assertSame('', trim($this->renderLanguages(['page' => 'a-page'])));
    }

    // A link onto the page already open leads nowhere: the language being read is named, and says so to a screen reader
    public function testTheLanguageBeingReadIsNotALink(): void
    {
        $rendered = $this->renderLanguages(['page' => 'a-page'], ['fr' => '/pages/contact?_locale=fr', 'en' => '/pages/contact?_locale=en']);

        $this->assertStringContainsString('<span class="menu-language-current" lang="fr" aria-current="true">FR</span>', $rendered);
        $this->assertStringNotContainsString('_locale=fr"', $rendered);
    }

    // These urls are query-string variants of pages the sitemap already declares once per language
    public function testTheLinksAreNotFollowed(): void
    {
        $this->assertStringContainsString('rel="nofollow"', $this->renderLanguages(['page' => 'a-page'], ['fr' => '/a?_locale=fr', 'en' => '/a?_locale=en']));
    }

    // The urls come from page_languages() alone, which builds them out of the routes: the fragment this replaces rewrote the current url by hand ("/fr/" replaced with "/en/"), which broke on any url the language code appeared twice in
    public function testTheUrlsAreTheOnesTheFunctionAnswers(): void
    {
        $template = $this->template('components/General/Languages.html.twig');

        $this->assertStringNotContainsString('app.request.requestUri', $template);
        $this->assertStringNotContainsString('replace(', $template);
    }

    // The condition pages/page.html.twig ships, read out of the template rather than restated here, and evaluated by Twig itself
    private function navbarPage(array $context): string
    {
        $this->assertSame(
            1,
            preg_match('/\{% set navbarPage = (.*?) %\}/', $this->template('pages/page.html.twig'), $matches),
            'pages/page.html.twig no longer declares navbarPage, this test can no longer read it.'
        );

        $twig = new Environment(new ArrayLoader(['condition' => '{% set navbarPage = ' . $matches[1] . ' %}{{ navbarPage is null ? "none" : "menu" }}']));

        return $twig->render('condition', $context);
    }

    public function testAPageDeclaresItselfToTheMenu(): void
    {
        $this->assertSame('menu', $this->navbarPage(['page' => 'a-page']));
    }

    // On /pages/parent/item-slug the Page is the parent: its languages name the parent's url, not the item's, so a visitor clicking one would lose the item
    public function testACollectionItemsDetailViewDeclaresNothing(): void
    {
        $this->assertSame('none', $this->navbarPage(['page' => 'a-page', 'detailHtml' => '<p>An item</p>']));
    }

    // The menu's urls are the public ones: an editor clicking a language would leave the preview, onto a 404 while the page is unpublished
    public function testThePreviewDeclaresNothing(): void
    {
        $this->assertSame('none', $this->navbarPage(['page' => 'a-page', 'isPreview' => true]));
    }
}
