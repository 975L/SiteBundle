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
        ]));

        $this->assertSame(
            ['https://nope.example.com/' => 0, 'https://example.com/' => 200],
            $client->check(['https://nope.example.com/', 'https://example.com/'])
        );
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
        }));

        $this->assertSame([], $client->findAssets('https://example.com', 'https://example.com'));
    }

    public function testFindAssetsReturnsEmptyArrayWhenThePageReferencesNone(): void
    {
        $client = new SmokeTestClient(new MockHttpClient(fn () => new MockResponse('<html><body><p>Rien.</p></body></html>')));

        $this->assertSame([], $client->findAssets('https://example.com', 'https://example.com'));
    }
}
