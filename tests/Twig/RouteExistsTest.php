<?php
/*
 * (c) 2019: 975L <contact@975l.com>
 * (c) 2019: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Twig;

use c975L\SiteBundle\Twig\RouteExists;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Route;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Routing\RouterInterface;

class RouteExistsTest extends TestCase
{
    private function createRouter(RouteCollection $collection): RouterInterface
    {
        $router = $this->createStub(RouterInterface::class);
        $router->method('getRouteCollection')->willReturn($collection);

        return $router;
    }

    // A registered route is returned (truthy)
    public function testRouteExistsReturnsRouteWhenRegistered(): void
    {
        $collection = new RouteCollection();
        $collection->add('page_display', new Route('/page/{page}'));

        $this->assertNotNull((new RouteExists($this->createRouter($collection)))->routeExists('page_display'));
    }

    // An unregistered route yields null
    public function testRouteExistsReturnsNullWhenNotRegistered(): void
    {
        $this->assertNull((new RouteExists($this->createRouter(new RouteCollection())))->routeExists('unknown_route'));
    }
}
