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
use c975L\SiteBundle\Management\ContentQualityHealthCheckProvider;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\ContentQualityClient;
use c975L\SiteBundle\Service\PageEditUrlResolver;
use c975L\SiteBundle\Service\PageExistenceChecker;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Tests\PagePublicUrlGeneratorTestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContentQualityHealthCheckProviderTest extends TestCase
{
    use PagePublicUrlGeneratorTestTrait;

    private const GOOD_ANALYSIS = ['hasDescription' => true, 'hasH1' => true, 'imagesWithoutAlt' => 0, 'internalLinks' => []];

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

    // request()'s return value is opaque to the provider - only read() (mocked separately per test) gives it meaning
    private function stubResponse(): ResponseInterface
    {
        return $this->createStub(ResponseInterface::class);
    }

    // Configures a ContentQualityClient stub/mock's request()/read() pair to behave like a synchronous analyze(), for tests that don't otherwise need to distinguish the two calls
    private function stubAnalyze(ContentQualityClient $client, array $analysis): void
    {
        $client->method('request')->willReturn($this->stubResponse());
        $client->method('read')->willReturn($analysis);
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

    private function createProvider(
        array $pages,
        ContentQualityClient $client,
        ?string $siteUrl = 'https://example.com',
        ?PageExistenceChecker $pageExistenceChecker = null,
    ): ContentQualityHealthCheckProvider {
        return new ContentQualityHealthCheckProvider(
            $this->createPageRepository($pages),
            $client,
            $this->createUrlResolver($siteUrl),
            $this->createPageEditUrlResolver(),
            $pageExistenceChecker ?? $this->createPageExistenceChecker(),
            $this->createTranslator(),
        );
    }

    public function testGetKindReturnsContentQuality(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $provider = $this->createProvider([], $client);

        $this->assertSame('content-quality', $provider->getKind());
    }

    public function testRunChecksReturnsEmptyArrayWithoutASiteUrl(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $provider = $this->createProvider([$this->createPage('home')], $client, null);

        $this->assertSame([], $provider->runChecks());
    }

    public function testRunChecksStatusIsOkWhenEverythingIsFine(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, self::GOOD_ANALYSIS);

        $provider = $this->createProvider([$this->createPage('home')], $client);

        $result = $provider->runChecks()[0];
        $this->assertSame(HealthCheckResult::STATUS_OK, $result['status']);
    }

    public function testRunChecksStatusIsWarningWhenDescriptionIsMissing(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, ['hasDescription' => false, 'hasH1' => true, 'imagesWithoutAlt' => 0, 'internalLinks' => []]);

        $provider = $this->createProvider([$this->createPage('home')], $client);

        $result = $provider->runChecks()[0];
        $this->assertSame(HealthCheckResult::STATUS_WARNING, $result['status']);
        $this->assertStringContainsString('label.health_check_content_quality_no_description', $result['summary']);
    }

    public function testRunChecksStatusIsWarningWhenH1IsMissing(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, ['hasDescription' => true, 'hasH1' => false, 'imagesWithoutAlt' => 0, 'internalLinks' => []]);

        $provider = $this->createProvider([$this->createPage('home')], $client);

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksStatusIsWarningWhenImagesAreMissingAlt(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, ['hasDescription' => true, 'hasH1' => true, 'imagesWithoutAlt' => 3, 'internalLinks' => []]);

        $provider = $this->createProvider([$this->createPage('home')], $client);

        $result = $provider->runChecks()[0];
        $this->assertSame(HealthCheckResult::STATUS_WARNING, $result['status']);
        $this->assertSame(3, $result['details']['imagesWithoutAlt']);
    }

    public function testRunChecksReturnsAWarningRowWhenThePageIsNotDeployed(): void
    {
        $client = $this->createMock(ContentQualityClient::class);
        $client->expects($this->never())->method('request');

        $provider = $this->createProvider([$this->createPage('home')], $client, pageExistenceChecker: $this->createPageExistenceChecker(false));

        $result = $provider->runChecks()[0];
        $this->assertSame(HealthCheckResult::STATUS_SKIPPED, $result['status']);
        $this->assertSame('label.health_check_page_not_found', $result['summary']);
    }

    public function testRunChecksReturnsAnErrorRowWhenTheCallFails(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $client->method('request')->willReturn($this->stubResponse());
        $client->method('read')->willThrowException(new \RuntimeException('Timeout'));

        $provider = $this->createProvider([$this->createPage('home')], $client);

        $result = $provider->runChecks()[0];
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $result['status']);
        $this->assertSame(['error' => 'Timeout'], $result['details']);
    }

    public function testRunChecksStatusIsErrorWhenAPageHasABrokenInternalLink(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, [
            'hasDescription' => true,
            'hasH1' => true,
            'imagesWithoutAlt' => 0,
            'internalLinks' => ['https://example.com/pages/missing/'],
        ]);
        $client->method('requestLinkCheck')->willReturn($this->stubResponse());
        $client->method('readLinkCheck')->willReturn(true);

        $provider = $this->createProvider([$this->createPage('home')], $client);

        $result = $provider->runChecks()[0];
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $result['status']);
        $this->assertSame(['https://example.com/pages/missing/'], $result['details']['brokenLinks']);
    }

    public function testRunChecksOnlyChecksEachUniqueLinkOnceAcrossAllPages(): void
    {
        $client = $this->createMock(ContentQualityClient::class);
        $this->stubAnalyze($client, [
            'hasDescription' => true,
            'hasH1' => true,
            'imagesWithoutAlt' => 0,
            'internalLinks' => ['https://example.com/pages/shared/'],
        ]);
        $client->expects($this->once())->method('requestLinkCheck')->with('https://example.com/pages/shared/')->willReturn($this->stubResponse());
        $client->method('readLinkCheck')->willReturn(false);

        $provider = $this->createProvider([$this->createPage('home'), $this->createPage('contact')], $client);

        $results = $provider->runChecks();
        $this->assertCount(2, $results);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[0]['status']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[1]['status']);
    }

    // A not-found page in the middle of the list must not shuffle the rows after it to the bottom - requests are fired concurrently (not-found pages skip firing one), but rows must still come back keyed to their own page's position
    public function testRunChecksKeepsRowsInPageOrderWhenAMiddlePageIsNotFound(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, self::GOOD_ANALYSIS);

        $pageExistenceChecker = $this->createStub(PageExistenceChecker::class);
        $pageExistenceChecker->method('exists')->willReturnCallback(
            static fn (string $url): bool => !str_contains($url, 'contact')
        );

        $provider = $this->createProvider(
            [$this->createPage('home'), $this->createPage('contact'), $this->createPage('about')],
            $client,
            pageExistenceChecker: $pageExistenceChecker,
        );

        $results = $provider->runChecks();

        $this->assertCount(3, $results);
        $this->assertSame('https://example.com/', $results[0]['url']);
        $this->assertSame(HealthCheckResult::STATUS_SKIPPED, $results[1]['status']);
        $this->assertSame('https://example.com/pages/about/', $results[2]['url']);
    }

    public function testRunChecksIncludesThePageEditUrl(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, self::GOOD_ANALYSIS);

        $provider = $this->createProvider([$this->createPage('home')], $client);

        $this->assertSame('/management/page/1/edit', $provider->runChecks()[0]['editUrl']);
    }
}
