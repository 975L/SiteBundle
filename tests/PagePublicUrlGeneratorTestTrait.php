<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests;

use c975L\ConfigBundle\Service\SiteLocales;
use c975L\SiteBundle\Service\PageTranslator;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;

// Shared by every test constructing a real PagePublicUrlResolver - a real UrlGenerator against the same route shapes as PageController, so tests exercise the actual routing rather than a hand-duplicated string (see PagePublicUrlResolverTest, the original of this helper)
trait PagePublicUrlGeneratorTestTrait
{
    private function createUrlGenerator(): UrlGenerator
    {
        $routes = new RouteCollection();
        $routes->add('page_home', new Route('/'));
        $routes->add('page_display', new Route('/pages/{page}', [], ['page' => '^(?!pdf)([a-zA-Z0-9\-\/]+)']));

        // The same pages in another language, as PageController declares them: the writing language keeps the two routes above, byte for byte
        $routes->add('page_home_localized', new Route('/{_locale}/'));
        $routes->add('page_display_localized', new Route('/{_locale}/pages/{page}', [], ['page' => '^(?!pdf)([a-zA-Z0-9\-\/]+)']));

        return new UrlGenerator($routes, new RequestContext());
    }

    // The languages a page was written in, which is what gates its "hreflang" group. A double and not the real service: reading them means a database, and only the tests about the group itself care what it answers - the others take the writing language alone, whose group is empty
    private function createPageTranslator(array $translatedLocales = ['fr']): PageTranslator
    {
        $pageTranslator = $this->createStub(PageTranslator::class);
        $pageTranslator->method('translatedLocales')->willReturn($translatedLocales);

        return $pageTranslator;
    }

    // The real SiteLocales rather than a double: it is the one place saying which languages a site speaks, and a test replacing it would stop testing the list a site actually declares
    private function createSiteLocales(array $enabledLocales = [], string $defaultLocale = 'fr'): SiteLocales
    {
        return new SiteLocales($enabledLocales, $defaultLocale);
    }
}
