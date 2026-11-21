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
use c975L\ConfigBundle\Service\HealthCheck;
use c975L\ConfigBundle\Service\SiteUrlResolver;
use c975L\SiteBundle\Service\MixedContentClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class MixedContentClientTest extends TestCase
{
    // The site's address, as "site-url" gives it
    private function siteUrlResolver(string $siteUrl = 'https://example.com'): SiteUrlResolver
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        return new SiteUrlResolver($configService);
    }

    public function testFindInsecureResourcesFindsAnInsecureImage(): void
    {
        $html = '<html><body><img src="http://example.com/photo.jpg"></body></html>';
        $client = new MixedContentClient(new MockHttpClient(fn () => new MockResponse($html)), $this->siteUrlResolver());

        $this->assertSame(['http://example.com/photo.jpg'], $client->findInsecureResources('https://example.com/pages/home/'));
    }

    public function testFindInsecureResourcesFindsAnInsecureStylesheet(): void
    {
        $html = '<html><head><link rel="stylesheet" href="http://example.com/style.css"></head></html>';
        $client = new MixedContentClient(new MockHttpClient(fn () => new MockResponse($html)), $this->siteUrlResolver());

        $this->assertSame(['http://example.com/style.css'], $client->findInsecureResources('https://example.com/pages/home/'));
    }

    public function testFindInsecureResourcesFindsScriptIframeSourceAudioAndVideo(): void
    {
        $html = '<html><body>'
            . '<script src="http://example.com/app.js"></script>'
            . '<iframe src="http://example.com/embed"></iframe>'
            . '<video><source src="http://example.com/video.mp4"></video>'
            . '<audio src="http://example.com/audio.mp3"></audio>'
            . '</body></html>';
        $client = new MixedContentClient(new MockHttpClient(fn () => new MockResponse($html)), $this->siteUrlResolver());

        $found = $client->findInsecureResources('https://example.com/pages/home/');
        $this->assertCount(4, $found);
        $this->assertContains('http://example.com/app.js', $found);
        $this->assertContains('http://example.com/embed', $found);
        $this->assertContains('http://example.com/video.mp4', $found);
        $this->assertContains('http://example.com/audio.mp3', $found);
    }

    public function testFindInsecureResourcesIgnoresPlainLinksAndSecureResources(): void
    {
        $html = '<html><body><a href="http://example.com/other-page/">Link</a><img src="https://example.com/photo.jpg"></body></html>';
        $client = new MixedContentClient(new MockHttpClient(fn () => new MockResponse($html)), $this->siteUrlResolver());

        $this->assertSame([], $client->findInsecureResources('https://example.com/pages/home/'));
    }

    public function testFindInsecureResourcesDeduplicatesRepeatedResources(): void
    {
        $html = '<html><body><img src="http://example.com/photo.jpg"><img src="http://example.com/photo.jpg"></body></html>';
        $client = new MixedContentClient(new MockHttpClient(fn () => new MockResponse($html)), $this->siteUrlResolver());

        $this->assertSame(['http://example.com/photo.jpg'], $client->findInsecureResources('https://example.com/pages/home/'));
    }

    public function testFindInsecureResourcesReturnsEmptyArrayWithNoMatch(): void
    {
        $html = '<html><body><p>Nothing here.</p></body></html>';
        $client = new MixedContentClient(new MockHttpClient(fn () => new MockResponse($html)), $this->siteUrlResolver());

        $this->assertSame([], $client->findInsecureResources('https://example.com/pages/home/'));
    }

    public function testFindInsecureResourcesSendsTheHealthCheckUserAgent(): void
    {
        $sentHeaders = [];
        $client = new MixedContentClient(new MockHttpClient(function (string $method, string $url, array $options) use (&$sentHeaders) {
            $sentHeaders = $options['headers'];

            return new MockResponse('<html></html>');
        }), $this->siteUrlResolver());

        $client->findInsecureResources('https://example.com/pages/home/');

        $this->assertContains('User-Agent: ' . HealthCheck::USER_AGENT, $sentHeaders);
    }

    // The site's own pages are read wherever the site runs, 127.0.0.1 in local dev included
    public function testTheSitePagesAreReadOnALocalAddress(): void
    {
        $html = '<html><body><img src="http://127.0.0.1:8000/photo.jpg"></body></html>';
        $client = new MixedContentClient(new MockHttpClient(fn () => new MockResponse($html)), $this->siteUrlResolver('http://127.0.0.1:8000'));

        $this->assertSame(['http://127.0.0.1:8000/photo.jpg'], $client->findInsecureResources('http://127.0.0.1:8000/pages/home/'));
    }

    // A page on another host is never read on the private network
    public function testAnotherHostOnThePrivateNetworkIsNeverRead(): void
    {
        $client = new MixedContentClient(new MockHttpClient(function (): MockResponse {
            $this->fail('The request reached the network.');
        }), $this->siteUrlResolver());

        $this->expectException(TransportException::class);

        $client->findInsecureResources('http://127.0.0.1/admin');
    }
}
