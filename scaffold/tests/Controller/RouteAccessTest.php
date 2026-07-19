<?php

namespace App\Tests\Controller;

class RouteAccessTest extends FunctionalTestCase
{
    private $client;

    public function setUp(): void
    {
        // Authenticated as admin: exercises real controller logic (including role-gated routes like page_preview) instead of being deflected by a login redirect, and stays safe if "site-maintenance" is enabled (see FunctionalTestCase)
        $this->client = $this->createAuthenticatedClient();
    }

    //Checks an URL matching no route at all still 404s (distinct from page_display's "route matched, entity missing" 404)
    public function testInexistingUrlReturns404(): void
    {
        $this->client->request('GET', '/inexisting-page');
        $this->assertEquals(404, $this->client->getResponse()->getStatusCode());
    }

    // Checks that no route (whatever bundles are installed on this site) crashes for an authenticated request. No per-route expectation to maintain: /management is already checked more strictly by ManagementAccessTest (must redirect to login when anonymous, not just avoid crashing), and real content is checked by ContentAccessTest.
    public function testNoRouteCrashes(): void
    {
        $router = static::getContainer()->get('router');
        $routes = $router->getRouteCollection();
        $failures = [];

        foreach ($routes as $name => $route) {
            if (str_starts_with($route->getPath(), '/management')) {
                continue;
            }

            $method = $route->getMethods()[0] ?? 'GET';
            $url = preg_replace('#\{[^}]+\}#', '1', $route->getPath());

            // Re-authenticates before every request: a previous route in this same loop may have logged the client out (app_logout is a route like any other here), which would otherwise make every route after it fail under "site-maintenance" for the wrong reason
            $this->client->loginUser($this->authenticatedUser);
            $this->client->request($method, $url);
            $status = $this->client->getResponse()->getStatusCode();
            if ($status >= 500) {
                $failures[] = sprintf('%s %s (route "%s") -> %d', $method, $url, $name, $status);
            }
        }

        $this->assertGreaterThan(0, \count($routes), 'Aucune route enregistrée');
        $this->assertEmpty($failures, implode("\n", $failures));
    }
}
