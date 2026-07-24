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
use c975L\SiteBundle\Management\W3cHtmlHealthCheckProvider;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PageEditUrlResolver;
use c975L\SiteBundle\Service\PageExistenceChecker;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\W3cValidatorClient;
use c975L\SiteBundle\Tests\PagePublicUrlGeneratorTestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class W3cHtmlHealthCheckProviderTest extends TestCase
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
            fn (string $id, array $parameters = [], ?string $domain = null) => strtr($id, $parameters)
        );

        return $translator;
    }

    // request()'s return value is opaque to the provider - only read() (mocked separately per test) gives it meaning
    private function stubResponse(): ResponseInterface
    {
        return $this->createStub(ResponseInterface::class);
    }

    private function createClient(array $html): W3cValidatorClient
    {
        $client = $this->createMock(W3cValidatorClient::class);
        $client->method('requestHtml')->willReturn($this->stubResponse());
        $client->method('readHtml')->willReturn($html);
        $client->expects($this->never())->method('requestCss');
        $client->expects($this->never())->method('readCss');

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
        ?PageExistenceChecker $pageExistenceChecker = null,
    ): W3cHtmlHealthCheckProvider {
        return new W3cHtmlHealthCheckProvider(
            $this->createPageRepository($pages),
            $client,
            $this->createUrlResolver($siteUrl),
            $this->createPageEditUrlResolver(),
            $pageExistenceChecker ?? $this->createPageExistenceChecker(),
            $this->createTranslator(),
        );
    }

    public function testGetKindReturnsW3cHtml(): void
    {
        $provider = $this->createProvider([], $this->createClient(['errors' => [], 'warnings' => []]));

        $this->assertSame('w3c-html', $provider->getKind());
    }

    public function testRunChecksReturnsEmptyArrayWithoutASiteUrl(): void
    {
        $provider = $this->createProvider([$this->createPage('home')], $this->createClient(['errors' => [], 'warnings' => []]), null);

        $this->assertSame([], $provider->runChecks());
    }

    public function testRunChecksStatusIsOkWithNoErrorsOrWarnings(): void
    {
        $provider = $this->createProvider([$this->createPage('home')], $this->createClient(['errors' => [], 'warnings' => []]));

        $this->assertSame(HealthCheckResult::STATUS_OK, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksStatusIsWarningWithOnlyWarnings(): void
    {
        $provider = $this->createProvider([$this->createPage('home')], $this->createClient(['errors' => [], 'warnings' => ['line 3: minor issue']]));

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksStatusIsErrorWhenThereAreErrors(): void
    {
        $provider = $this->createProvider([$this->createPage('home')], $this->createClient(['errors' => ['line 1: bad element'], 'warnings' => []]));

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $provider->runChecks()[0]['status']);
    }

    // Every request() must be fired before any read() is called, so the HttpClient transport can run them concurrently - a page whose read() throws must not stop the other pages from getting their own row
    public function testRunChecksFiresEveryRequestBeforeReadingAnyResponse(): void
    {
        $requested = [];
        $client = $this->createStub(W3cValidatorClient::class);
        $client->method('requestHtml')->willReturnCallback(function (string $url) use (&$requested) {
            $requested[] = $url;

            return $this->stubResponse();
        });
        $client->method('readHtml')->willReturnCallback(
            fn () => ['errors' => [], 'warnings' => []]
        );

        $provider = $this->createProvider([$this->createPage('home'), $this->createPage('contact')], $client);

        $results = $provider->runChecks();

        $this->assertCount(2, $results);
        $this->assertSame(['https://example.com/', 'https://example.com/pages/contact/'], $requested);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[0]['status']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[1]['status']);
    }

    // A not-found page in the middle of the list must not shuffle the rows after it to the bottom - requests are fired concurrently (a not-found page skips firing one), but rows must still come back keyed to their own page's position
    public function testRunChecksKeepsRowsInPageOrderWhenAMiddlePageIsNotFound(): void
    {
        $client = $this->createClient(['errors' => [], 'warnings' => []]);

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

    public function testRunChecksReturnsASkippedRowWhenThePageIsNotDeployed(): void
    {
        $client = $this->createMock(W3cValidatorClient::class);
        $client->expects($this->never())->method('requestHtml');
        $client->expects($this->never())->method('requestCss');

        $provider = $this->createProvider([$this->createPage('home')], $client, pageExistenceChecker: $this->createPageExistenceChecker(false));

        $result = $provider->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_SKIPPED, $result['status']);
        $this->assertSame('label.health_check_page_not_found', $result['summary']);
    }

    public function testRunChecksReturnsAnErrorRowWhenTheCallFails(): void
    {
        $client = $this->createStub(W3cValidatorClient::class);
        $client->method('requestHtml')->willReturn($this->stubResponse());
        $client->method('readHtml')->willThrowException(new \RuntimeException('Timeout'));

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
