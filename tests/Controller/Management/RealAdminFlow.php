<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Controller\Management;

use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use EasyCorp\Bundle\EasyAdminBundle\Provider\AdminContextProvider;
use EasyCorp\Bundle\EasyAdminBundle\Registry\AdminControllerRegistry;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminRouteGenerator;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Cache\Adapter\ArrayAdapter;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\RequestStack;
use Symfony\Component\HttpFoundation\Session\Session;
use Symfony\Component\HttpFoundation\Session\Storage\MockArraySessionStorage;
use Symfony\Component\Routing\Generator\UrlGenerator;
use Symfony\Component\Routing\Matcher\UrlMatcher;
use Symfony\Component\Routing\RequestContext;
use Symfony\Component\Routing\RouteCollection;
use Symfony\Component\Security\Csrf\CsrfTokenManager;
use Symfony\Component\Security\Csrf\TokenGenerator\UriSafeTokenGenerator;
use Symfony\Component\Security\Csrf\TokenStorage\SessionTokenStorage;

// EasyAdmin's own route and url generators and Symfony's own csrf manager, none of them doubled: a stubbed generator answers any action with a url, a real one sends an action holding no #[AdminRoute] to the dashboard
final class RealAdminFlow
{
    public readonly AdminUrlGenerator $urlGenerator;
    public readonly CsrfTokenManager $csrfTokenManager;
    public readonly RequestStack $requestStack;
    private readonly RouteCollection $routes;

    /** @param class-string $crudController */
    public function __construct(string $crudController)
    {
        $cache = new ArrayAdapter();
        $dashboard = new #[AdminDashboard(routePath: '/management', routeName: 'management')] class extends AbstractDashboardController {
        };
        $routeGenerator = new AdminRouteGenerator([$dashboard], [new \ReflectionClass($crudController)->newInstanceWithoutConstructor()], $cache);
        $this->routes = $routeGenerator->generateAll();

        $request = new Request();
        $request->setSession(new Session(new MockArraySessionStorage()));
        $this->requestStack = new RequestStack([$request]);

        $this->csrfTokenManager = new CsrfTokenManager(new UriSafeTokenGenerator(), new SessionTokenStorage($this->requestStack));
        $this->urlGenerator = new AdminUrlGenerator(
            new AdminContextProvider($this->requestStack),
            new UrlGenerator($this->routes, new RequestContext()),
            new AdminControllerRegistry($cache),
            $routeGenerator,
            $cache,
        );
    }

    // What the router makes of a url the way a click would reach it
    /** @return array<string, mixed> */
    public function routeOf(string $url): array
    {
        return new UrlMatcher($this->routes, new RequestContext())->match((string) parse_url($url, \PHP_URL_PATH));
    }
}
