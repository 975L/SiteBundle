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

    // The component as it ships, rendered against stubs of the four things it reads outside itself - the two language readers being what it picks between on whether a Page is behind the screen
    private function renderLanguages(array $context, array $languages = [], array $screenLanguages = [], array $query = []): string
    {
        $twig = new Environment(new ArrayLoader(['languages' => $this->template('components/General/Languages.html.twig')]));
        $twig->addFunction(new TwigFunction('page_languages', static fn (mixed $page): array => $languages));
        $twig->addFunction(new TwigFunction('screen_languages', static fn (): array => $screenLanguages));
        $twig->addFilter(new TwigFilter('trans', static fn (string $id): string => $id));
        $twig->addFilter(new TwigFilter('language_name', static fn (string $locale): string => strtoupper($locale)));

        return $twig->render('languages', $context + ['app' => ['request' => ['locale' => 'fr', 'query' => ['all' => $query]]]]);
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

        $this->assertStringContainsString('action="/pages/contact"', $rendered);
        $this->assertStringContainsString('name="_locale"', $rendered);
        $this->assertStringContainsString('<option value="en" lang="en">EN</option>', $rendered);
    }

    // A screen answering in one language alone - a back-office url, an endpoint, a token url - whether the layout hands a null or nothing at all
    public function testAScreenAnsweringInOneLanguageRendersNothingAtAll(): void
    {
        $this->assertSame('', trim($this->renderLanguages(['page' => null])));
        $this->assertSame('', trim($this->renderLanguages([])));
    }

    // With no Page behind the screen the bar is built from the screen's own languages instead: a shop listing, a product sheet, a campaign, a basket are the owning bundle's interface and answer in every language the site declares
    public function testAScreenWithNoPageOffersTheLanguagesItAnswersIn(): void
    {
        $rendered = $this->renderLanguages(['page' => null], screenLanguages: ['fr' => '/shop?_locale=fr', 'en' => '/shop?_locale=en']);

        $this->assertStringContainsString('action="/shop"', $rendered);
        $this->assertStringContainsString('<option value="en" lang="en">EN</option>', $rendered);
    }

    // A page nobody translated: page_languages() answers an empty list, and a bar with a single choice is no choice
    public function testAPageWrittenInASingleLanguageRendersNothing(): void
    {
        $this->assertSame('', trim($this->renderLanguages(['page' => 'a-page'])));
    }

    // The language being read is the one the field opens on, so picking it back is a submission that changes nothing rather than a choice missing from the list
    public function testTheLanguageBeingReadIsTheOneSelected(): void
    {
        $rendered = $this->renderLanguages(['page' => 'a-page'], ['fr' => '/pages/contact?_locale=fr', 'en' => '/pages/contact?_locale=en']);

        $this->assertStringContainsString('<option value="fr" lang="fr" selected>FR</option>', $rendered);
    }

    // Nothing to follow at all: a crawler used to be handed one query-string variant per language of a page the sitemap already declares once per language, and told not to follow them
    public function testTheMenuHoldsNoLinkAtAll(): void
    {
        $this->assertStringNotContainsString('<a ', $this->renderLanguages(['page' => 'a-page'], ['fr' => '/a?_locale=fr', 'en' => '/a?_locale=en']));
    }

    // A GET submission drops the query string the action carries, so what the visitor is reading under - a page number, a filter - is written as fields; the language they are leaving is not, the field itself carrying it
    public function testWhatTheVisitorIsReadingUnderRidesAlong(): void
    {
        $rendered = $this->renderLanguages(
            ['page' => null],
            screenLanguages: ['fr' => '/shop?_locale=fr', 'en' => '/shop?_locale=en'],
            query: ['page' => '2', '_locale' => 'fr', 'filters' => ['a', 'b']]
        );

        $this->assertStringContainsString('<input type="hidden" name="page" value="2">', $rendered);
        $this->assertStringNotContainsString('name="_locale" value=', $rendered);
        $this->assertStringNotContainsString('name="filters"', $rendered);
    }

    // Without javascript the button is what sends the choice, so it ships in the markup and the controller takes it away
    public function testTheChoiceIsSentWithoutJavascriptToo(): void
    {
        $rendered = $this->renderLanguages(['page' => 'a-page'], ['fr' => '/a?_locale=fr', 'en' => '/a?_locale=en']);

        $this->assertStringContainsString('type="submit"', $rendered);
        $this->assertStringContainsString('data-languages-target="submit"', $rendered);
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
