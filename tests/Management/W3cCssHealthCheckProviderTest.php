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
use c975L\ConfigBundle\Service\UrlStatusChecker;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\W3cCssHealthCheckProvider;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PageEditUrlResolver;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\W3cValidatorClient;
use c975L\SiteBundle\Tests\PagePublicUrlGeneratorTestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class W3cCssHealthCheckProviderTest extends TestCase
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

    private function createUrlResolver(?string $siteUrl = 'https://example.com'): PagePublicUrlResolver
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        return new PagePublicUrlResolver($configService, $this->createUrlGenerator(), $this->createSiteLocales());
    }

    private function createUrlStatusChecker(bool $exists = true): UrlStatusChecker
    {
        $checker = $this->createStub(UrlStatusChecker::class);
        $checker->method('exists')->willReturn($exists);

        return $checker;
    }

    private function createTranslator(): TranslatorInterface
    {
        // Parameters appended after the id rather than substituted into it: the ids here carry no placeholder of their own, and the counts a summary was built with are exactly what these tests assert on
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $id, array $parameters = [], ?string $domain = null) => $parameters ? $id . ' ' . implode(' ', $parameters) : $id
        );

        return $translator;
    }

    // request()'s return value is opaque to the provider - only read() (mocked separately per test) gives it meaning
    private function stubResponse(): ResponseInterface
    {
        return $this->createStub(ResponseInterface::class);
    }

    private function createClient(array $css): W3cValidatorClient
    {
        $client = $this->createMock(W3cValidatorClient::class);
        $client->method('requestCss')->willReturn($this->stubResponse());
        $client->method('readCss')->willReturn($css);
        $client->expects($this->never())->method('requestHtml');
        $client->expects($this->never())->method('readHtml');

        return $client;
    }

    private function createPageEditUrlResolver(string $url = '/management/page/1/edit'): PageEditUrlResolver
    {
        $resolver = $this->createStub(PageEditUrlResolver::class);
        $resolver->method('resolve')->willReturn($url);

        return $resolver;
    }

    private function createProvider(
        array $pages,
        W3cValidatorClient $client,
        ?string $siteUrl = 'https://example.com',
        ?UrlStatusChecker $urlStatusChecker = null,
    ): W3cCssHealthCheckProvider {
        return new W3cCssHealthCheckProvider(
            $this->createPageRepository($pages),
            $client,
            $this->createUrlResolver($siteUrl),
            $this->createPageEditUrlResolver(),
            $urlStatusChecker ?? $this->createUrlStatusChecker(),
            $this->createTranslator(),
        );
    }

    public function testGetKindReturnsW3cCss(): void
    {
        $provider = $this->createProvider([], $this->createClient(['errors' => [], 'warnings' => []]));

        $this->assertSame('w3c-css', $provider->getKind());
    }

    // Throws rather than returning empty: the kind is exhaustive, so an empty run would tell HealthCheckRunner to clear every stored row of it
    public function testRunChecksThrowsWithoutASiteUrl(): void
    {
        $provider = $this->createProvider([$this->createPage('home')], $this->createClient(['errors' => [], 'warnings' => []]), null);

        $this->expectException(\RuntimeException::class);

        $provider->runChecks();
    }

    public function testRunChecksStatusIsOkWithNoErrorsOrWarnings(): void
    {
        $provider = $this->createProvider([$this->createPage('home')], $this->createClient(['errors' => [], 'warnings' => []]));

        $this->assertSame(HealthCheckResult::STATUS_OK, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksStatusIsWarningWithOnlyWarnings(): void
    {
        $provider = $this->createProvider([$this->createPage('home')], $this->createClient(['errors' => [], 'warnings' => ['line 9: minor issue']]));

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksStatusIsErrorWhenThereAreErrors(): void
    {
        $provider = $this->createProvider([$this->createPage('home')], $this->createClient(['errors' => ['line 4: bad property'], 'warnings' => []]));

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $provider->runChecks()[0]['status']);
    }

    // A stylesheet built on custom properties raises one benign warning per var() usage - the row must not sit at orange for good over warnings nobody can act on
    public function testRunChecksStatusIsOkWhenEveryWarningIsBenign(): void
    {
        $provider = $this->createProvider([$this->createPage('home')], $this->createClient([
            'errors' => [],
            'warnings' => [],
            'benignWarnings' => ['line 2: CSS variables', 'line 3: vendor extension'],
        ]));

        $this->assertSame(HealthCheckResult::STATUS_OK, $provider->runChecks()[0]['status']);
    }

    // Nothing is hidden: the total shown is exactly the W3C report's own (actionable + benign), the breakdown only added next to it - a dashboard disagreeing with the report it links to reads as broken
    public function testRunChecksSummaryKeepsTheW3cWarningTotalAndAddsTheBreakdown(): void
    {
        $provider = $this->createProvider([$this->createPage('home')], $this->createClient([
            'errors' => [],
            'warnings' => ['line 7: deprecated property'],
            'benignWarnings' => ['line 2: CSS variables', 'line 3: vendor extension'],
        ]));

        $summary = $provider->runChecks()[0]['summary'];

        $this->assertStringContainsString('label.health_check_summary_w3c_css', $summary);
        // %errors% 0, %warnings% 3 (1 actionable + 2 benign), then %actionable% 1 / %benign% 2
        $this->assertStringContainsString('0 3', $summary);
        $this->assertStringContainsString('label.health_check_w3c_benign_warnings', $summary);
        $this->assertStringContainsString('1 2', $summary);
    }

    // Nothing to break down, nothing appended - and the HTML validator, which has no benign class at all, never shows it
    public function testRunChecksSummaryOmitsTheBreakdownWithoutBenignWarnings(): void
    {
        $provider = $this->createProvider([$this->createPage('home')], $this->createClient(['errors' => [], 'warnings' => ['line 7: deprecated property']]));

        $this->assertStringNotContainsString('label.health_check_w3c_benign_warnings', $provider->runChecks()[0]['summary']);
    }

    // Split off, never dropped: both lists are persisted, so the report can always be reconciled with the row
    public function testRunChecksKeepsBenignWarningsInTheDetails(): void
    {
        $provider = $this->createProvider([$this->createPage('home')], $this->createClient([
            'errors' => [],
            'warnings' => [],
            'benignWarnings' => ['line 2: CSS variables'],
        ]));

        $this->assertSame(['line 2: CSS variables'], $provider->runChecks()[0]['details']['benignWarnings']);
    }

    public function testRunChecksReturnsASkippedRowWhenThePageIsNotDeployed(): void
    {
        $client = $this->createMock(W3cValidatorClient::class);
        $client->expects($this->never())->method('requestHtml');
        $client->expects($this->never())->method('requestCss');

        $provider = $this->createProvider([$this->createPage('home')], $client, urlStatusChecker: $this->createUrlStatusChecker(false));

        $result = $provider->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_SKIPPED, $result['status']);
        $this->assertSame('label.health_check_page_not_found', $result['summary']);
    }

    public function testRunChecksReturnsAnErrorRowWhenTheCallFails(): void
    {
        $client = $this->createStub(W3cValidatorClient::class);
        $client->method('requestCss')->willReturn($this->stubResponse());
        $client->method('readCss')->willThrowException(new \RuntimeException('Timeout'));

        $provider = $this->createProvider([$this->createPage('home')], $client);

        $result = $provider->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $result['status']);
        $this->assertSame(['error' => 'Timeout'], $result['details']);
    }

    public function testRunChecksIncludesThePageEditUrl(): void
    {
        $provider = $this->createProvider([$this->createPage('home')], $this->createClient(['errors' => [], 'warnings' => []]));

        $this->assertSame('/management/page/1/edit', $provider->runChecks()[0]['editUrl']);
    }
}
