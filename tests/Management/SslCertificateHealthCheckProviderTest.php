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
use c975L\SiteBundle\Management\SslCertificateHealthCheckProvider;
use c975L\SiteBundle\Service\SslCertificateClient;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class SslCertificateHealthCheckProviderTest extends TestCase
{
    private function createConfigService(?string $siteUrl): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        return $configService;
    }

    private function createClient(): SslCertificateClient
    {
        return $this->createStub(SslCertificateClient::class);
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $id, array $parameters = []) => strtr($id, $parameters)
        );

        return $translator;
    }

    public function testGetKindReturnsSslCertificate(): void
    {
        $provider = new SslCertificateHealthCheckProvider($this->createConfigService(null), $this->createClient(), $this->createTranslator());

        $this->assertSame('ssl-certificate', $provider->getKind());
    }

    public function testRunChecksReturnsEmptyArrayWithoutASiteUrl(): void
    {
        $provider = new SslCertificateHealthCheckProvider($this->createConfigService(null), $this->createClient(), $this->createTranslator());

        $this->assertSame([], $provider->runChecks());
    }

    public function testRunChecksSkipsAHttpSiteUrl(): void
    {
        $provider = new SslCertificateHealthCheckProvider($this->createConfigService('http://example.com'), $this->createClient(), $this->createTranslator());

        $result = $provider->runChecks()[0];
        $this->assertSame(HealthCheckResult::STATUS_SKIPPED, $result['status']);
    }

    public function testRunChecksStatusIsOkWhenFarFromExpiry(): void
    {
        $client = $this->createClient();
        $client->method('fetchExpiry')->willReturn(new \DateTimeImmutable('+89 days'));

        $provider = new SslCertificateHealthCheckProvider($this->createConfigService('https://example.com'), $client, $this->createTranslator());

        $result = $provider->runChecks()[0];
        $this->assertSame(HealthCheckResult::STATUS_OK, $result['status']);
        $this->assertSame('https://example.com', $result['url']);
    }

    public function testRunChecksStatusIsWarningWithinThirtyDays(): void
    {
        $client = $this->createClient();
        $client->method('fetchExpiry')->willReturn(new \DateTimeImmutable('+15 days'));

        $provider = new SslCertificateHealthCheckProvider($this->createConfigService('https://example.com'), $client, $this->createTranslator());

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksStatusIsErrorWithinSevenDays(): void
    {
        $client = $this->createClient();
        $client->method('fetchExpiry')->willReturn(new \DateTimeImmutable('+3 days'));

        $provider = new SslCertificateHealthCheckProvider($this->createConfigService('https://example.com'), $client, $this->createTranslator());

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksStatusIsErrorWhenAlreadyExpired(): void
    {
        $client = $this->createClient();
        $client->method('fetchExpiry')->willReturn(new \DateTimeImmutable('-2 days'));

        $provider = new SslCertificateHealthCheckProvider($this->createConfigService('https://example.com'), $client, $this->createTranslator());

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksReturnsAnErrorRowWhenTheCallFails(): void
    {
        $client = $this->createStub(SslCertificateClient::class);
        $client->method('fetchExpiry')->willThrowException(new \RuntimeException('Connection refused'));

        $provider = new SslCertificateHealthCheckProvider($this->createConfigService('https://example.com'), $client, $this->createTranslator());

        $result = $provider->runChecks()[0];
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $result['status']);
        $this->assertSame(['error' => 'Connection refused'], $result['details']);
    }
}
