<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests;

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

        return new UrlGenerator($routes, new RequestContext());
    }
}
