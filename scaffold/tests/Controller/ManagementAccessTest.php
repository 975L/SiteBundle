<?php

namespace App\Tests\Controller;

use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

// Anonymous, not FunctionalTestCase: /management stays reachable during maintenance (see MaintenanceListener) and ManagementAuthenticationListener runs before it anyway - this test is exactly about the anonymous case, so it must not log in.
class ManagementAccessTest extends WebTestCase
{
    private $client;

    public function setUp(): void
    {
        $this->client = static::createClient();
    }

    //Checks every /management route redirects an anonymous visitor to the login page
    public function testAllManagementRoutesRedirectAnonymousToLogin(): void
    {
        $router = static::getContainer()->get('router');
        $failures = [];

        foreach ($router->getRouteCollection() as $name => $route) {
            if (!str_starts_with($route->getPath(), '/management')) {
                continue;
            }

            $method = $route->getMethods()[0] ?? 'GET';
            $url = preg_replace('#\{[^}]+\}#', '1', $route->getPath());

            $this->client->request($method, $url);
            $response = $this->client->getResponse();
            if (302 !== $response->getStatusCode() || !str_contains((string) $response->headers->get('Location'), '/login')) {
                $failures[] = sprintf('%s %s (route "%s") -> %d', $method, $url, $name, $response->getStatusCode());
            }
        }

        $this->assertEmpty($failures, implode("\n", $failures));
    }
}
