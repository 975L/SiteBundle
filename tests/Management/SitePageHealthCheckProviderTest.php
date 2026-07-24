<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\SitePageHealthCheckProvider;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PageEditUrlResolver;
use c975L\SiteBundle\Service\PageExistenceChecker;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\PageSpeedInsightsClient;
use c975L\SiteBundle\Tests\PagePublicUrlGeneratorTestTrait;
use c975L\SiteBundle\Tests\Repository\ConfigRepositoryFindOneBySlugFixture;
use c975L\UiBundle\Service\ConfigEditUrlResolver;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class SitePageHealthCheckProviderTest extends TestCase
{
    use PagePublicUrlGeneratorTestTrait;

    private function createPage(string $slug, string $title): Page
    {
        $page = new Page();
        $page->setSlug($slug);
        $page->setTitle($title);

        return $page;
    }

    private function createPageRepository(array $pages): PageRepository
    {
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findAllOrdered')->willReturn($pages);

        return $repository;
    }

    // $apiKey defaults to a set value, so tests focused on per-page behaviour don't also get the "missing key" row mixed into $results[0]
    private function createConfigService(?string $siteUrl, ?string $apiKey = 'some-key'): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([
            ['site-url', $siteUrl],
            ['healthcheck-pagespeed-api-key', $apiKey],
        ]);

        return $configService;
    }

    // request()'s return value is opaque to the provider - only read() (mocked separately per test) gives it meaning
    private function stubResponse(): ResponseInterface
    {
        return $this->createStub(ResponseInterface::class);
    }

    private function createPageExistenceChecker(bool $exists = true): PageExistenceChecker
    {
        $checker = $this->createStub(PageExistenceChecker::class);
        $checker->method('exists')->willReturn($exists);

        return $checker;
    }

    private function createConfigRepository(?Config $config = null): ConfigRepository
    {
        return new ConfigRepositoryFindOneBySlugFixture($config);
    }

    private function createConfigEditUrlResolver(string $url = '/management/config/1/edit'): ConfigEditUrlResolver
    {
        $resolver = $this->createStub(ConfigEditUrlResolver::class);
        $resolver->method('resolve')->willReturn($url);

        return $resolver;
    }

    private function createPageEditUrlResolver(string $url = '/management/page/1/edit'): PageEditUrlResolver
    {
        $resolver = $this->createStub(PageEditUrlResolver::class);
        $resolver->method('resolve')->willReturn($url);

        return $resolver;
    }

    // Mimics Symfony's translator closely enough for assertions: substitutes %placeholder% params directly into the translation id, since the id itself carries them in these keys
    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $id, array $parameters = [], ?string $domain = null) => strtr($id, $parameters)
        );

        return $translator;
    }

    private function createProvider(
        PageRepository $pageRepository,
        PageSpeedInsightsClient $pageSpeedInsightsClient,
        ConfigServiceInterface $configService,
        ?ConfigRepository $configRepository = null,
        ?ConfigEditUrlResolver $configEditUrlResolver = null,
        ?PageExistenceChecker $pageExistenceChecker = null,
        ?PageEditUrlResolver $pageEditUrlResolver = null,
    ): SitePageHealthCheckProvider {
        return new SitePageHealthCheckProvider(
            $pageRepository,
            $pageSpeedInsightsClient,
            new PagePublicUrlResolver($configService, $this->createUrlGenerator()),
            $pageEditUrlResolver ?? $this->createPageEditUrlResolver(),
            $pageExistenceChecker ?? $this->createPageExistenceChecker(),
            $configService,
            $configRepository ?? $this->createConfigRepository(),
            $configEditUrlResolver ?? $this->createConfigEditUrlResolver(),
            $this->createTranslator(),
        );
    }

    public function testGetKindReturnsPagespeed(): void
    {
        $provider = $this->createProvider(
            $this->createPageRepository([]),
            $this->createStub(PageSpeedInsightsClient::class),
            $this->createConfigService('https://example.com'),
        );

        $this->assertSame('pagespeed', $provider->getKind());
    }

    public function testRunChecksReturnsEmptyArrayWithoutASiteUrl(): void
    {
        $provider = $this->createProvider(
            $this->createPageRepository([$this->createPage('home', 'Home')]),
            $this->createStub(PageSpeedInsightsClient::class),
            $this->createConfigService(null),
        );

        $this->assertSame([], $provider->runChecks());
    }

    public function testRunChecksBuildsTheHomeUrlFromTheSiteRootWithoutThePagesPrefix(): void
    {
        $pageSpeedInsightsClient = $this->createMock(PageSpeedInsightsClient::class);
        $pageSpeedInsightsClient->expects($this->once())
            ->method('request')
            ->with('https://example.com/')
            ->willReturn($this->stubResponse());
        $pageSpeedInsightsClient->method('read')
            ->willReturn(['scores' => ['performance' => 95, 'accessibility' => 95, 'best-practices' => 95, 'seo' => 95], 'consoleErrors' => []]);

        $provider = $this->createProvider(
            $this->createPageRepository([$this->createPage('home', 'Home')]),
            $pageSpeedInsightsClient,
            $this->createConfigService('https://example.com'),
        );

        $results = $provider->runChecks();

        $this->assertSame('https://example.com/', $results[0]['url']);
    }

    public function testRunChecksBuildsARegularPageUrlWithTrailingSlash(): void
    {
        $pageSpeedInsightsClient = $this->createMock(PageSpeedInsightsClient::class);
        $pageSpeedInsightsClient->expects($this->once())
            ->method('request')
            ->with('https://example.com/pages/contact/')
            ->willReturn($this->stubResponse());
        $pageSpeedInsightsClient->method('read')
            ->willReturn(['scores' => ['performance' => 95, 'accessibility' => 95, 'best-practices' => 95, 'seo' => 95], 'consoleErrors' => []]);

        $provider = $this->createProvider(
            $this->createPageRepository([$this->createPage('contact', 'Contact')]),
            $pageSpeedInsightsClient,
            $this->createConfigService('https://example.com'),
        );

        $results = $provider->runChecks();

        $this->assertSame('https://example.com/pages/contact/', $results[0]['url']);
    }

    public function testRunChecksStatusIsOkWhenEveryScoreIsAtLeastNinety(): void
    {
        $pageSpeedInsightsClient = $this->createStub(PageSpeedInsightsClient::class);
        $pageSpeedInsightsClient->method('request')->willReturn($this->stubResponse());
        $pageSpeedInsightsClient->method('read')->willReturn([
            'scores' => ['performance' => 90, 'accessibility' => 100, 'best-practices' => 95, 'seo' => 92],
            'consoleErrors' => [],
        ]);

        $provider = $this->createProvider(
            $this->createPageRepository([$this->createPage('home', 'Home')]),
            $pageSpeedInsightsClient,
            $this->createConfigService('https://example.com'),
        );

        $this->assertSame(HealthCheckResult::STATUS_OK, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksStatusIsWarningWhenAScoreIsBetweenFiftyAndNinety(): void
    {
        $pageSpeedInsightsClient = $this->createStub(PageSpeedInsightsClient::class);
        $pageSpeedInsightsClient->method('request')->willReturn($this->stubResponse());
        $pageSpeedInsightsClient->method('read')->willReturn([
            'scores' => ['performance' => 65, 'accessibility' => 100, 'best-practices' => 95, 'seo' => 92],
            'consoleErrors' => [],
        ]);

        $provider = $this->createProvider(
            $this->createPageRepository([$this->createPage('home', 'Home')]),
            $pageSpeedInsightsClient,
            $this->createConfigService('https://example.com'),
        );

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksStatusIsErrorWhenAScoreIsBelowFifty(): void
    {
        $pageSpeedInsightsClient = $this->createStub(PageSpeedInsightsClient::class);
        $pageSpeedInsightsClient->method('request')->willReturn($this->stubResponse());
        $pageSpeedInsightsClient->method('read')->willReturn([
            'scores' => ['performance' => 30, 'accessibility' => 100, 'best-practices' => 95, 'seo' => 92],
            'consoleErrors' => [],
        ]);

        $provider = $this->createProvider(
            $this->createPageRepository([$this->createPage('home', 'Home')]),
            $pageSpeedInsightsClient,
            $this->createConfigService('https://example.com'),
        );

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksStatusIsAtLeastWarningWhenThereAreConsoleErrorsEvenWithPerfectScores(): void
    {
        $pageSpeedInsightsClient = $this->createStub(PageSpeedInsightsClient::class);
        $pageSpeedInsightsClient->method('request')->willReturn($this->stubResponse());
        $pageSpeedInsightsClient->method('read')->willReturn([
            'scores' => ['performance' => 100, 'accessibility' => 100, 'best-practices' => 100, 'seo' => 100],
            'consoleErrors' => ['Uncaught TypeError'],
        ]);

        $provider = $this->createProvider(
            $this->createPageRepository([$this->createPage('home', 'Home')]),
            $pageSpeedInsightsClient,
            $this->createConfigService('https://example.com'),
        );

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksIncludesThePageEditUrl(): void
    {
        $pageSpeedInsightsClient = $this->createStub(PageSpeedInsightsClient::class);
        $pageSpeedInsightsClient->method('request')->willReturn($this->stubResponse());
        $pageSpeedInsightsClient->method('read')->willReturn([
            'scores' => ['performance' => 95, 'accessibility' => 95, 'best-practices' => 95, 'seo' => 95],
            'consoleErrors' => [],
        ]);

        $provider = $this->createProvider(
            $this->createPageRepository([$this->createPage('home', 'Home')]),
            $pageSpeedInsightsClient,
            $this->createConfigService('https://example.com'),
            pageEditUrlResolver: $this->createPageEditUrlResolver('/management/page/1/edit'),
        );

        $this->assertSame('/management/page/1/edit', $provider->runChecks()[0]['editUrl']);
    }

    public function testRunChecksReturnsAnErrorRowWhenThePageSpeedCallFails(): void
    {
        $pageSpeedInsightsClient = $this->createStub(PageSpeedInsightsClient::class);
        $pageSpeedInsightsClient->method('request')->willReturn($this->stubResponse());
        $pageSpeedInsightsClient->method('read')->willThrowException(new \RuntimeException('Quota exceeded'));

        $provider = $this->createProvider(
            $this->createPageRepository([$this->createPage('home', 'Home')]),
            $pageSpeedInsightsClient,
            $this->createConfigService('https://example.com'),
        );

        $result = $provider->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $result['status']);
        $this->assertSame(['error' => 'Quota exceeded'], $result['details']);
    }

    public function testRunChecksReturnsASkippedRowWithoutCallingPageSpeedWhenThePageDoesNotExist(): void
    {
        $pageSpeedInsightsClient = $this->createMock(PageSpeedInsightsClient::class);
        $pageSpeedInsightsClient->expects($this->never())->method('request');

        $provider = $this->createProvider(
            $this->createPageRepository([$this->createPage('home', 'Home')]),
            $pageSpeedInsightsClient,
            $this->createConfigService('https://example.com'),
            pageExistenceChecker: $this->createPageExistenceChecker(false),
        );

        $result = $provider->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_SKIPPED, $result['status']);
        $this->assertSame('label.health_check_page_not_found', $result['summary']);
    }

    // A not-found page in the middle of the list must not shuffle the rows after it to the bottom - PSI requests are fired concurrently (a not-found page skips firing one), but rows must still come back keyed to their own page's position
    public function testRunChecksKeepsRowsInPageOrderWhenAMiddlePageIsNotFound(): void
    {
        $pageSpeedInsightsClient = $this->createStub(PageSpeedInsightsClient::class);
        $pageSpeedInsightsClient->method('request')->willReturn($this->stubResponse());
        $pageSpeedInsightsClient->method('read')->willReturn([
            'scores' => ['performance' => 95, 'accessibility' => 95, 'best-practices' => 95, 'seo' => 95],
            'consoleErrors' => [],
        ]);

        $pageExistenceChecker = $this->createStub(PageExistenceChecker::class);
        $pageExistenceChecker->method('exists')->willReturnCallback(
            static fn (string $url): bool => !str_contains($url, 'contact')
        );

        $provider = $this->createProvider(
            $this->createPageRepository([$this->createPage('home', 'Home'), $this->createPage('contact', 'Contact'), $this->createPage('about', 'About')]),
            $pageSpeedInsightsClient,
            $this->createConfigService('https://example.com'),
            pageExistenceChecker: $pageExistenceChecker,
        );

        $results = $provider->runChecks();

        $this->assertCount(3, $results);
        $this->assertSame('https://example.com/', $results[0]['url']);
        $this->assertSame(HealthCheckResult::STATUS_SKIPPED, $results[1]['status']);
        $this->assertSame('https://example.com/pages/about/', $results[2]['url']);
    }

    public function testRunChecksPrependsAWarningRowWhenNoApiKeyIsConfigured(): void
    {
        $pageSpeedInsightsClient = $this->createStub(PageSpeedInsightsClient::class);
        $pageSpeedInsightsClient->method('request')->willReturn($this->stubResponse());
        $pageSpeedInsightsClient->method('read')->willReturn([
            'scores' => ['performance' => 100, 'accessibility' => 100, 'best-practices' => 100, 'seo' => 100],
            'consoleErrors' => [],
        ]);

        $provider = $this->createProvider(
            $this->createPageRepository([$this->createPage('home', 'Home')]),
            $pageSpeedInsightsClient,
            $this->createConfigService('https://example.com', apiKey: null),
            configEditUrlResolver: $this->createConfigEditUrlResolver('/management/config/1/edit'),
        );

        $results = $provider->runChecks();

        $this->assertCount(2, $results);
        $this->assertSame('/management/config/1/edit', $results[0]['url']);
        $this->assertSame(HealthCheckResult::STATUS_WARNING, $results[0]['status']);
        $this->assertSame('https://example.com/', $results[1]['url']);
    }

    public function testRunChecksDoesNotPrependAWarningRowWhenAnApiKeyIsConfigured(): void
    {
        $pageSpeedInsightsClient = $this->createStub(PageSpeedInsightsClient::class);
        $pageSpeedInsightsClient->method('request')->willReturn($this->stubResponse());
        $pageSpeedInsightsClient->method('read')->willReturn([
            'scores' => ['performance' => 100, 'accessibility' => 100, 'best-practices' => 100, 'seo' => 100],
            'consoleErrors' => [],
        ]);

        $provider = $this->createProvider(
            $this->createPageRepository([$this->createPage('home', 'Home')]),
            $pageSpeedInsightsClient,
            $this->createConfigService('https://example.com', apiKey: 'some-key'),
        );

        $this->assertCount(1, $provider->runChecks());
    }

    public function testRunChecksPassesTheApiKeyConfigEntityToTheUrlResolver(): void
    {
        $config = (new Config())->setSlug('healthcheck-pagespeed-api-key');
        $configRepository = new ConfigRepositoryFindOneBySlugFixture($config);

        $resolvedConfig = null;
        $configEditUrlResolver = $this->createStub(ConfigEditUrlResolver::class);
        $configEditUrlResolver->method('resolve')->willReturnCallback(function (?Config $c) use (&$resolvedConfig) {
            $resolvedConfig = $c;

            return '/management/config/1/edit';
        });

        $provider = $this->createProvider(
            $this->createPageRepository([]),
            $this->createStub(PageSpeedInsightsClient::class),
            $this->createConfigService('https://example.com', apiKey: null),
            configRepository: $configRepository,
            configEditUrlResolver: $configEditUrlResolver,
        );

        $results = $provider->runChecks();

        $this->assertSame('healthcheck-pagespeed-api-key', $configRepository->getRequestedSlug());
        $this->assertSame($config, $resolvedConfig);
        $this->assertSame('/management/config/1/edit', $results[0]['url']);
    }
}
