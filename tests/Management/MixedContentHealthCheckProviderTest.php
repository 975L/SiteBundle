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
use c975L\SiteBundle\Management\MixedContentHealthCheckProvider;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\MixedContentClient;
use c975L\SiteBundle\Service\PageEditUrlResolver;
use c975L\SiteBundle\Service\PageExistenceChecker;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Tests\PagePublicUrlGeneratorTestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class MixedContentHealthCheckProviderTest extends TestCase
{
    use PagePublicUrlGeneratorTestTrait;

    private function createPage(string $slug): Page
    {
        $page = new Page();
        $page->setSlug($slug);
        $page->setTitle($slug);

        return $page;
    }

    private function createPageRepository(array $pages): PageRepository
    {
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findAllOrdered')->willReturn($pages);

        return $repository;
    }

    private function createConfigService(?string $siteUrl): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        return $configService;
    }

    private function createUrlResolver(?string $siteUrl = 'https://example.com'): PagePublicUrlResolver
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        return new PagePublicUrlResolver($configService, $this->createUrlGenerator());
    }

    private function createPageExistenceChecker(bool $exists = true): PageExistenceChecker
    {
        $checker = $this->createStub(PageExistenceChecker::class);
        $checker->method('exists')->willReturn($exists);

        return $checker;
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $id, array $parameters = []) => strtr($id, $parameters)
        );

        return $translator;
    }

    private function createPageEditUrlResolver(string $url = '/management/page/1/edit'): PageEditUrlResolver
    {
        $resolver = $this->createStub(PageEditUrlResolver::class);
        $resolver->method('resolve')->willReturn($url);

        return $resolver;
    }

    private function createProvider(array $pages, MixedContentClient $client, ?string $siteUrl = 'https://example.com', bool $pageExists = true): MixedContentHealthCheckProvider
    {
        return new MixedContentHealthCheckProvider(
            $this->createPageRepository($pages),
            $client,
            $this->createUrlResolver($siteUrl),
            $this->createPageEditUrlResolver(),
            $this->createPageExistenceChecker($pageExists),
            $this->createConfigService($siteUrl),
            $this->createTranslator(),
        );
    }

    public function testGetKindReturnsMixedContent(): void
    {
        $provider = $this->createProvider([], $this->createStub(MixedContentClient::class));

        $this->assertSame('mixed-content', $provider->getKind());
    }

    public function testRunChecksReturnsEmptyArrayWhenSiteIsNotHttps(): void
    {
        $provider = $this->createProvider([$this->createPage('home')], $this->createStub(MixedContentClient::class), 'http://example.com');

        $this->assertSame([], $provider->runChecks());
    }

    public function testRunChecksReturnsEmptyArrayWithoutASiteUrl(): void
    {
        $provider = $this->createProvider([$this->createPage('home')], $this->createStub(MixedContentClient::class), null);

        $this->assertSame([], $provider->runChecks());
    }

    public function testRunChecksStatusIsOkWhenNoInsecureResourceFound(): void
    {
        $client = $this->createStub(MixedContentClient::class);
        $client->method('findInsecureResources')->willReturn([]);

        $provider = $this->createProvider([$this->createPage('home')], $client);

        $result = $provider->runChecks()[0];
        $this->assertSame(HealthCheckResult::STATUS_OK, $result['status']);
    }

    public function testRunChecksStatusIsErrorWhenInsecureResourcesAreFound(): void
    {
        $client = $this->createStub(MixedContentClient::class);
        $client->method('findInsecureResources')->willReturn(['http://example.com/logo.png']);

        $provider = $this->createProvider([$this->createPage('home')], $client);

        $result = $provider->runChecks()[0];
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $result['status']);
        $this->assertSame(['http://example.com/logo.png'], $result['details']['insecureResources']);
    }

    public function testRunChecksReturnsASkippedRowWhenThePageIsNotDeployed(): void
    {
        $client = $this->createMock(MixedContentClient::class);
        $client->expects($this->never())->method('findInsecureResources');

        $provider = $this->createProvider([$this->createPage('home')], $client, 'https://example.com', false);

        $result = $provider->runChecks()[0];
        $this->assertSame(HealthCheckResult::STATUS_SKIPPED, $result['status']);
    }

    public function testRunChecksReturnsAnErrorRowWhenTheCallFails(): void
    {
        $client = $this->createStub(MixedContentClient::class);
        $client->method('findInsecureResources')->willThrowException(new \RuntimeException('Timeout'));

        $provider = $this->createProvider([$this->createPage('home')], $client);

        $result = $provider->runChecks()[0];
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $result['status']);
        $this->assertSame(['error' => 'Timeout'], $result['details']);
    }

    public function testRunChecksIncludesThePageEditUrl(): void
    {
        $client = $this->createStub(MixedContentClient::class);
        $client->method('findInsecureResources')->willReturn([]);

        $provider = $this->createProvider([$this->createPage('home')], $client);

        $this->assertSame('/management/page/1/edit', $provider->runChecks()[0]['editUrl']);
    }
}
