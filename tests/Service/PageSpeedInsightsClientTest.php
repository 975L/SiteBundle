<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Service\PageSpeedInsightsClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class PageSpeedInsightsClientTest extends TestCase
{
    private function createConfigService(?string $apiKey): ConfigServiceInterface
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($apiKey);

        return $configService;
    }

    private function pageSpeedResponse(array $scores, array $consoleErrorItems = []): string
    {
        $categories = [];
        foreach ($scores as $category => $score) {
            $categories[$category] = ['score' => $score];
        }

        return json_encode([
            'lighthouseResult' => [
                'categories' => $categories,
                'audits' => [
                    'errors-in-console' => [
                        'details' => ['items' => $consoleErrorItems],
                    ],
                ],
            ],
        ]);
    }

    public function testAnalyzeParsesScoresAsPercentages(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse(
                $this->pageSpeedResponse([
                    'performance' => 0.82,
                    'accessibility' => 0.91,
                    'best-practices' => 1.0,
                    'seo' => 0.95,
                ]),
                ['http_code' => 200]
            )
        );

        $client = new PageSpeedInsightsClient($httpClient, $this->createConfigService('some-key'));
        $analysis = $client->analyze('https://example.com/pages/home/');

        $this->assertSame(
            ['performance' => 82, 'accessibility' => 91, 'best-practices' => 100, 'seo' => 95],
            $analysis['scores']
        );
        $this->assertSame([], $analysis['consoleErrors']);
    }

    public function testAnalyzeExtractsConsoleErrorDescriptions(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse(
                $this->pageSpeedResponse(
                    ['performance' => 0.5, 'accessibility' => 0.5, 'best-practices' => 0.5, 'seo' => 0.5],
                    [['description' => 'Uncaught TypeError: foo is not a function'], ['description' => 'Failed to load resource']],
                ),
                ['http_code' => 200]
            )
        );

        $client = new PageSpeedInsightsClient($httpClient, $this->createConfigService(null));
        $analysis = $client->analyze('https://example.com/pages/contact/');

        $this->assertSame(
            ['Uncaught TypeError: foo is not a function', 'Failed to load resource'],
            $analysis['consoleErrors']
        );
    }

    public function testAnalyzeRequestsAllFourCategoriesForTheGivenUrl(): void
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$requestedUrl) {
                $requestedUrl = $url;

                return new MockResponse($this->pageSpeedResponse([]), ['http_code' => 200]);
            }
        );

        $client = new PageSpeedInsightsClient($httpClient, $this->createConfigService('my-key'));
        $client->analyze('https://example.com/pages/home/');

        $this->assertStringContainsString('url=https%3A%2F%2Fexample.com%2Fpages%2Fhome%2F', $requestedUrl);
        $this->assertStringContainsString('key=my-key', $requestedUrl);
        $this->assertStringContainsString('category=performance', $requestedUrl);
        $this->assertStringContainsString('category=accessibility', $requestedUrl);
        $this->assertStringContainsString('category=best-practices', $requestedUrl);
        $this->assertStringContainsString('category=seo', $requestedUrl);
    }

    public function testAnalyzeOmitsTheKeyParameterWhenNoApiKeyIsConfigured(): void
    {
        $requestedUrl = null;
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$requestedUrl) {
                $requestedUrl = $url;

                return new MockResponse($this->pageSpeedResponse([]), ['http_code' => 200]);
            }
        );

        $client = new PageSpeedInsightsClient($httpClient, $this->createConfigService(null));
        $client->analyze('https://example.com/pages/home/');

        $this->assertStringNotContainsString('key=', $requestedUrl);
    }

    public function testAnalyzeWrapsTransportExceptionsWithoutTheUrl(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse('', ['error' => 'Connection refused'])
        );

        $client = new PageSpeedInsightsClient($httpClient, $this->createConfigService('my-key'));

        try {
            $client->analyze('https://example.com/pages/home/');
            $this->fail('Expected a RuntimeException.');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('my-key', $e->getMessage());
            $this->assertStringNotContainsString('googleapis.com', $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
    }

    public function testAnalyzeThrowsAClearMessageOn429WithoutAnApiKey(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse('', ['http_code' => 429])
        );

        $client = new PageSpeedInsightsClient($httpClient, $this->createConfigService(null));

        try {
            $client->analyze('https://example.com/pages/home/');
            $this->fail('Expected a RuntimeException.');
        } catch (\RuntimeException $e) {
            $this->assertStringContainsString('healthcheck-pagespeed-api-key', $e->getMessage());
        }
    }

    public function testAnalyzeWrapsThe429WithoutTheUrlWhenAnApiKeyIsConfigured(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse('', ['http_code' => 429])
        );

        $client = new PageSpeedInsightsClient($httpClient, $this->createConfigService('some-key'));

        try {
            $client->analyze('https://example.com/pages/home/');
            $this->fail('Expected a RuntimeException.');
        } catch (\RuntimeException $e) {
            $this->assertSame('PageSpeed Insights returned HTTP 429', $e->getMessage());
            $this->assertStringNotContainsString('some-key', $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
    }

    // "HTTP 500" alone had a page whose analysis never ran read as a page found broken. What Google answers says which of the two it is, and it is the sentence the reader acts on
    public function testAnalyzeCarriesGooglesOwnReasonAlongsideTheStatusCode(): void
    {
        $body = json_encode(['error' => ['code' => 500, 'message' => 'Lighthouse returned error: ERRORED_DOCUMENT_REQUEST. Status code: 503']]);
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse($body, ['http_code' => 500])
        );

        $client = new PageSpeedInsightsClient($httpClient, $this->createConfigService('my-key'));

        try {
            $client->analyze('https://example.com/pages/home/');
            $this->fail('Expected a RuntimeException.');
        } catch (\RuntimeException $e) {
            $this->assertSame('PageSpeed Insights returned HTTP 500 : Lighthouse returned error: ERRORED_DOCUMENT_REQUEST. Status code: 503', $e->getMessage());
            $this->assertStringNotContainsString('my-key', $e->getMessage());
            $this->assertNull($e->getPrevious());
        }
    }

    // An error page that is not Google's json - a proxy's html, an empty body - still has to leave a usable message rather than take the row down with it
    public function testAnalyzeFallsBackToTheStatusCodeWhenTheBodyCarriesNoReason(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse('<html>Bad gateway</html>', ['http_code' => 502])
        );

        $client = new PageSpeedInsightsClient($httpClient, $this->createConfigService('my-key'));

        try {
            $client->analyze('https://example.com/pages/home/');
            $this->fail('Expected a RuntimeException.');
        } catch (\RuntimeException $e) {
            $this->assertSame('PageSpeed Insights returned HTTP 502', $e->getMessage());
        }
    }

    // The key is not in what Google answers, and the day it is, it does not reach a row summary read on a dashboard
    public function testAnalyzeRedactsAKeyEchoedBackInTheReason(): void
    {
        $body = json_encode(['error' => ['message' => 'Invalid request: key=my-key is not authorized']]);
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse($body, ['http_code' => 403])
        );

        $client = new PageSpeedInsightsClient($httpClient, $this->createConfigService('my-key'));

        try {
            $client->analyze('https://example.com/pages/home/');
            $this->fail('Expected a RuntimeException.');
        } catch (\RuntimeException $e) {
            $this->assertStringNotContainsString('my-key', $e->getMessage());
            $this->assertStringContainsString('key=***', $e->getMessage());
        }
    }
}
