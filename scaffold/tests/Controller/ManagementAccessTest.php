<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

// Anonymous, not FunctionalTestCase: /management stays reachable during maintenance (see MaintenanceListener) and ManagementAuthenticationListener runs before it anyway - this test is exactly about the anonymous case, so it must not log in.
class ManagementAccessTest extends WebTestCase
{
    private KernelBrowser $client;

    public function setUp(): void
    {
        $this->client = static::createClient();
    }

    // Checks every /management route redirects an anonymous visitor to the login page
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

    // Signed in is not enough: access_control asks the back-office voter, which a plain member does not answer
    public function testManagementRefusesAnAuthenticatedVisitorWithoutTheBackOfficeBar(): void
    {
        $this->client->loginUser($this->createMember());
        $this->client->request('GET', '/management');

        $this->assertSame(403, $this->client->getResponse()->getStatusCode());
    }

    // Persisted because EntityUserProvider::refreshUser() reloads it by id - dama/doctrine-test-bundle rolls the transaction back, nothing reaches the database
    private function createMember(): User
    {
        $user = new User()
            ->setEmail('management-access@example.test')
            ->setPassword('not-used')
            ->setRoles(['ROLE_USER'])
            ->setIsEnabled(true)
            ->setIsVerified(true)
            ->setCreation(new \DateTime())
            ->setModification(new \DateTime());

        $entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $entityManager->persist($user);
        $entityManager->flush();

        return $user;
    }
}
