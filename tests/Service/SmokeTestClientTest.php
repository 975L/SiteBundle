<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Service\SmokeTestClient;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class SmokeTestClientTest extends TestCase
{
    public function testCheckReturnsTheStatusCodeOfEachUrl(): void
    {
        $client = new SmokeTestClient(new MockHttpClient([
            new MockResponse('', ['http_code' => 200]),
            new MockResponse('', ['http_code' => 404]),
            new MockResponse('', ['http_code' => 500]),
        ]));

        $this->assertSame(
            ['https://example.com/' => 200, 'https://example.com/pages/gone' => 404, 'https://example.com/pages/broken' => 500],
            $client->check(['https://example.com/', 'https://example.com/pages/gone', 'https://example.com/pages/broken'])
        );
    }

    // A url that never answers must not abort the run: the remaining urls still have to be checked, and this one is reported as 0
    public function testCheckReportsZeroForAnUnreachableUrlAndKeepsGoing(): void
    {
        $client = new SmokeTestClient(new MockHttpClient([
            new MockResponse('', ['error' => 'Could not resolve host']),
            new MockResponse('', ['http_code' => 200]),
            new MockResponse('', ['error' => 'Could not resolve host']),
        ]), 0);

        $this->assertSame(
            ['https://nope.example.com/' => 0, 'https://example.com/' => 200],
            $client->check(['https://nope.example.com/', 'https://example.com/'])
        );
    }

    // One request reset by php-fpm on a healthy site must not fail a deployment: only that url is tried again, and it keeps its place in the result
    public function testCheckTriesATransientStatusAgainAndKeepsTheOrder(): void
    {
        $requested = [];
        $answers = [503, 200, 200];
        $client = new SmokeTestClient(new MockHttpClient(
            static function (string $method, string $url) use (&$requested, &$answers): MockResponse {
                $requested[] = $url;

                return new MockResponse('', ['http_code' => array_shift($answers)]);
            }
        ), 0);

        $this->assertSame(
            ['https://example.com/item-1' => 200, 'https://example.com/item-2' => 200],
            $client->check(['https://example.com/item-1', 'https://example.com/item-2'])
        );
        $this->assertSame(['https://example.com/item-1', 'https://example.com/item-2', 'https://example.com/item-1'], $requested);
    }

    // The second try is the last one: its answer is what gets reported
    public function testCheckReportsATransientStatusThatLasts(): void
    {
        $requests = 0;
        $client = new SmokeTestClient(new MockHttpClient(
            static function () use (&$requests): MockResponse {
                ++$requests;

                return new MockResponse('', ['http_code' => 503]);
            }
        ), 0);

        $this->assertSame(['https://example.com/' => 503], $client->check(['https://example.com/']));
        $this->assertSame(2, $requests);
    }

    // A 500 or a 404 comes from the application: a second try would only delay the report
    public function testCheckDoesNotTryAnApplicationErrorAgain(): void
    {
        $requests = 0;
        $client = new SmokeTestClient(new MockHttpClient(
            static function () use (&$requests): MockResponse {
                ++$requests;

                return new MockResponse('', ['http_code' => 500]);
            }
        ), 0);

        $this->assertSame(['https://example.com/' => 500], $client->check(['https://example.com/']));
        $this->assertSame(1, $requests);
    }

    public function testCheckKeepsEveryUrlInOrderAcrossBatches(): void
    {
        $urls = array_map(static fn (int $i): string => 'https://example.com/page-' . $i, range(1, 25));
        $client = new SmokeTestClient(new MockHttpClient(static fn (string $method, string $url) => new MockResponse('', [
            'http_code' => str_ends_with($url, '-17') ? 500 : 200,
        ])));

        $statuses = $client->check($urls);

        $this->assertSame($urls, array_keys($statuses));
        $this->assertSame(500, $statuses['https://example.com/page-17']);
        $this->assertCount(24, array_filter($statuses, static fn (int $status): bool => 200 === $status));
    }

    public function testCheckReturnsAnEmptyArrayWithoutUrls(): void
    {
        $client = new SmokeTestClient(new MockHttpClient([]));

        $this->assertSame([], $client->check([]));
    }

    public function testFindAssetsResolvesRelativeUrlsAgainstTheBaseUrl(): void
    {
        $html = '<html><head><link rel="stylesheet" href="/assets/styles/app-EiPntxm.css">'
            . '<script src="/assets/app-ZxxqoUJ.js"></script></head></html>';
        $client = new SmokeTestClient(new MockHttpClient(fn () => new MockResponse($html)));

        $this->assertSame(
            ['https://example.com/assets/styles/app-EiPntxm.css', 'https://example.com/assets/app-ZxxqoUJ.js'],
            $client->findAssets('https://example.com', 'https://example.com')
        );
    }

    public function testFindAssetsKeepsAbsoluteUrlsAndTheirCacheBustingQuery(): void
    {
        $html = '<html><head><link rel="stylesheet" href="https://example.com/bundles/build/site.css?v=1785051467"></head></html>';
        $client = new SmokeTestClient(new MockHttpClient(fn () => new MockResponse($html)));

        $this->assertSame(
            ['https://example.com/bundles/build/site.css?v=1785051467'],
            $client->findAssets('https://example.com', 'https://example.com')
        );
    }

    // A trailing slash on either side must not produce a double slash, which would 404 on some servers
    public function testFindAssetsDoesNotDoubleTheSlashBetweenBaseUrlAndAsset(): void
    {
        $html = '<html><head><script src="/assets/app.js"></script></head></html>';
        $client = new SmokeTestClient(new MockHttpClient(fn () => new MockResponse($html)));

        $this->assertSame(
            ['https://example.com/assets/app.js'],
            $client->findAssets('https://example.com/', 'https://example.com/')
        );
    }

    public function testFindAssetsIgnoresNonAssetLinksAndDeduplicates(): void
    {
        $html = '<html><head><link rel="canonical" href="https://example.com/pages/home">'
            . '<link rel="icon" href="/favicon.ico">'
            . '<script src="/assets/app.js"></script><script src="/assets/app.js"></script></head>'
            . '<body><a href="/pages/contact">Contact</a><img src="/photo.jpg"></body></html>';
        $client = new SmokeTestClient(new MockHttpClient(fn () => new MockResponse($html)));

        $this->assertSame(['https://example.com/assets/app.js'], $client->findAssets('https://example.com', 'https://example.com'));
    }

    public function testFindAssetsReturnsEmptyArrayWhenThePageCannotBeFetched(): void
    {
        $client = new SmokeTestClient(new MockHttpClient(function (): never {
            throw new TransportException('Connection refused');
        }), 0);

        $this->assertSame([], $client->findAssets('https://example.com', 'https://example.com'));
    }

    // The home page reset once by php-fpm must not fail the run on "no asset found"
    public function testFindAssetsTriesATransientStatusAgain(): void
    {
        $answers = [
            new MockResponse('', ['http_code' => 503]),
            new MockResponse('<html><head><script src="/assets/app.js"></script></head></html>', ['http_code' => 200]),
        ];
        $client = new SmokeTestClient(new MockHttpClient($answers), 0);

        $this->assertSame(['https://example.com/assets/app.js'], $client->findAssets('https://example.com', 'https://example.com'));
    }

    // A second try is the last one, and a 500 comes from the application so it gets none
    #[DataProvider('giveUpStatusProvider')]
    public function testFindAssetsGivesUpOnAStatusThatLastsOrIsNotTransient(int $status, int $expectedRequests): void
    {
        $requests = 0;
        $client = new SmokeTestClient(new MockHttpClient(
            static function () use (&$requests, $status): MockResponse {
                ++$requests;

                return new MockResponse('', ['http_code' => $status]);
            }
        ), 0);

        $this->assertSame([], $client->findAssets('https://example.com', 'https://example.com'));
        $this->assertSame($expectedRequests, $requests);
    }

    public static function giveUpStatusProvider(): array
    {
        return [
            'transient' => [503, 2],
            'application error' => [500, 1],
        ];
    }

    public function testFindAssetsReturnsEmptyArrayWhenThePageReferencesNone(): void
    {
        $client = new SmokeTestClient(new MockHttpClient(fn () => new MockResponse('<html><body><p>Rien.</p></body></html>')));

        $this->assertSame([], $client->findAssets('https://example.com', 'https://example.com'));
    }

    // A run fires dozens of urls at the site itself: without c975LHealthCheck in the agent, HealthCheck::isProbe() no longer matches and the front rate limiter answers 429, which check() reports as a broken page
    public function testEveryRequestCarriesAProbeUserAgent(): void
    {
        $userAgents = [];
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$userAgents): MockResponse {
                $userAgents[] = $options['normalized_headers']['user-agent'][0] ?? '';

                return new MockResponse('<html></html>', ['http_code' => 200]);
            }
        );

        $client = new SmokeTestClient($httpClient);
        $client->check(['https://example.com/']);
        $client->findAssets('https://example.com/', 'https://example.com');

        $this->assertCount(2, $userAgents);
        $this->assertSame($userAgents[0], $userAgents[1]);
        $this->assertStringContainsString('c975LHealthCheck', $userAgents[0]);
    }
}
