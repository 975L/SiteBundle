<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\SecurityHeadersHealthCheckProvider;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\SecurityHeadersClient;
use c975L\SiteBundle\Tests\PagePublicUrlGeneratorTestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class SecurityHeadersHealthCheckProviderTest extends TestCase
{
    use PagePublicUrlGeneratorTestTrait;

    private const ALL_HEADERS_SET = [
        'strict-transport-security' => 'max-age=31536000',
        'x-content-type-options' => 'nosniff',
        'content-security-policy' => "default-src 'self'",
        'referrer-policy' => 'no-referrer',
        'permissions-policy' => 'geolocation=()',
        'x-frame-options' => 'DENY',
    ];

    private function createPage(string $slug): Page
    {
        $page = new Page();
        $page->setSlug($slug);
        $page->setTitle($slug);

        return $page;
    }

    private function createPageRepository(?Page $home): PageRepository
    {
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findOneBySlug')->willReturn($home);

        return $repository;
    }

    private function createUrlResolver(?string $url = 'https://example.com/'): PagePublicUrlResolver
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($url ? 'https://example.com' : null);

        return new PagePublicUrlResolver($configService, $this->createUrlGenerator());
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $id, array $parameters = [], ?string $domain = null) => strtr($id, $parameters)
        );

        return $translator;
    }

    private function createClient(array $headers): SecurityHeadersClient
    {
        $client = $this->createStub(SecurityHeadersClient::class);
        $client->method('fetchHeaders')->willReturn($headers);

        return $client;
    }

    public function testGetKindReturnsSecurityHeaders(): void
    {
        $provider = new SecurityHeadersHealthCheckProvider(
            $this->createPageRepository(null),
            $this->createClient([]),
            $this->createUrlResolver(),
            $this->createTranslator(),
        );

        $this->assertSame('security-headers', $provider->getKind());
    }

    public function testRunChecksReturnsEmptyArrayWithoutASiteUrl(): void
    {
        $provider = new SecurityHeadersHealthCheckProvider(
            $this->createPageRepository($this->createPage('home')),
            $this->createClient([]),
            $this->createUrlResolver(null),
            $this->createTranslator(),
        );

        $this->assertSame([], $provider->runChecks());
    }

    public function testRunChecksStatusIsOkWhenEveryRecommendedHeaderIsPresent(): void
    {
        $provider = new SecurityHeadersHealthCheckProvider(
            $this->createPageRepository($this->createPage('home')),
            $this->createClient(self::ALL_HEADERS_SET),
            $this->createUrlResolver(),
            $this->createTranslator(),
        );

        $result = $provider->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_OK, $result['status']);
        $this->assertSame([], $result['details']['missing']);
    }

    public function testRunChecksAcceptsCspFrameAncestorsInPlaceOfXFrameOptions(): void
    {
        $headers = self::ALL_HEADERS_SET;
        unset($headers['x-frame-options']);
        $headers['content-security-policy'] = "default-src 'self'; frame-ancestors 'none'";

        $provider = new SecurityHeadersHealthCheckProvider(
            $this->createPageRepository($this->createPage('home')),
            $this->createClient($headers),
            $this->createUrlResolver(),
            $this->createTranslator(),
        );

        $this->assertSame(HealthCheckResult::STATUS_OK, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksStatusIsWarningWhenOneOrTwoHeadersAreMissing(): void
    {
        $headers = self::ALL_HEADERS_SET;
        unset($headers['permissions-policy']);

        $provider = new SecurityHeadersHealthCheckProvider(
            $this->createPageRepository($this->createPage('home')),
            $this->createClient($headers),
            $this->createUrlResolver(),
            $this->createTranslator(),
        );

        $result = $provider->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $result['status']);
        $this->assertSame(['permissions-policy'], $result['details']['missing']);
    }

    public function testRunChecksStatusIsErrorWhenThreeOrMoreHeadersAreMissing(): void
    {
        $headers = ['strict-transport-security' => 'max-age=31536000'];

        $provider = new SecurityHeadersHealthCheckProvider(
            $this->createPageRepository($this->createPage('home')),
            $this->createClient($headers),
            $this->createUrlResolver(),
            $this->createTranslator(),
        );

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksFlagsAWildcardCorsHeaderEvenWithEveryOtherHeaderPresent(): void
    {
        $headers = self::ALL_HEADERS_SET;
        $headers['access-control-allow-origin'] = '*';

        $provider = new SecurityHeadersHealthCheckProvider(
            $this->createPageRepository($this->createPage('home')),
            $this->createClient($headers),
            $this->createUrlResolver(),
            $this->createTranslator(),
        );

        $result = $provider->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $result['status']);
        $this->assertStringContainsString('label.health_check_security_headers_cors_wildcard', $result['summary']);
    }

    public function testRunChecksReturnsAnErrorRowWhenTheCallFails(): void
    {
        $client = $this->createStub(SecurityHeadersClient::class);
        $client->method('fetchHeaders')->willThrowException(new \RuntimeException('Connection refused'));

        $provider = new SecurityHeadersHealthCheckProvider(
            $this->createPageRepository($this->createPage('home')),
            $client,
            $this->createUrlResolver(),
            $this->createTranslator(),
        );

        $result = $provider->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $result['status']);
        $this->assertSame(['error' => 'Connection refused'], $result['details']);
    }
}
