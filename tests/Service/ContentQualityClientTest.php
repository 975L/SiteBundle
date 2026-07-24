<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Service\ContentQualityClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class ContentQualityClientTest extends TestCase
{
    private function htmlResponse(string $html): MockHttpClient
    {
        return new MockHttpClient(fn (string $method, string $url, array $options) => new MockResponse($html, ['http_code' => 200]));
    }

    public function testAnalyzeDetectsAPresentDescriptionAndH1(): void
    {
        $html = '<html><head><meta name="description" content="A great page"></head><body><h1>Title</h1></body></html>';
        $client = new ContentQualityClient($this->htmlResponse($html));

        $result = $client->analyze('https://example.com/pages/home/');

        $this->assertTrue($result['hasDescription']);
        $this->assertTrue($result['hasH1']);
        $this->assertSame(0, $result['imagesWithoutAlt']);
    }

    public function testAnalyzeDetectsAMissingDescriptionAndH1(): void
    {
        $html = '<html><head></head><body><p>No heading here</p></body></html>';
        $client = new ContentQualityClient($this->htmlResponse($html));

        $result = $client->analyze('https://example.com/pages/home/');

        $this->assertFalse($result['hasDescription']);
        $this->assertFalse($result['hasH1']);
    }

    public function testAnalyzeTreatsABlankDescriptionAsMissing(): void
    {
        $html = '<html><head><meta name="description" content="   "></head><body><h1>Title</h1></body></html>';
        $client = new ContentQualityClient($this->htmlResponse($html));

        $this->assertFalse($client->analyze('https://example.com/pages/home/')['hasDescription']);
    }

    public function testAnalyzeCountsImagesWithoutAlt(): void
    {
        $html = '<html><body><h1>T</h1><img src="a.jpg" alt="Described"><img src="b.jpg"><img src="c.jpg" alt=""></body></html>';
        $client = new ContentQualityClient($this->htmlResponse($html));

        $this->assertSame(2, $client->analyze('https://example.com/pages/home/')['imagesWithoutAlt']);
    }

    public function testAnalyzeExtractsRootRelativeAndSameHostAbsoluteLinksOnly(): void
    {
        $html = '<html><body><h1>T</h1>
            <a href="/pages/contact/">Contact</a>
            <a href="https://example.com/pages/about/">About</a>
            <a href="https://external.com/">External</a>
            <a href="#top">Anchor</a>
            <a href="mailto:hello@example.com">Mail</a>
        </body></html>';
        $client = new ContentQualityClient($this->htmlResponse($html));

        $links = $client->analyze('https://example.com/pages/home/')['internalLinks'];

        $this->assertSame(['https://example.com/pages/contact/', 'https://example.com/pages/about/'], $links);
    }

    public function testAnalyzeDedupesInternalLinks(): void
    {
        $html = '<html><body><h1>T</h1><a href="/pages/contact/">A</a><a href="/pages/contact/">B</a></body></html>';
        $client = new ContentQualityClient($this->htmlResponse($html));

        $this->assertSame(['https://example.com/pages/contact/'], $client->analyze('https://example.com/pages/home/')['internalLinks']);
    }

    public function testIsLinkBrokenReturnsTrueForA4xxStatus(): void
    {
        $httpClient = new MockHttpClient(fn (string $method, string $url, array $options) => new MockResponse('', ['http_code' => 404]));
        $client = new ContentQualityClient($httpClient);

        $this->assertTrue($client->isLinkBroken('https://example.com/pages/missing/'));
    }

    public function testIsLinkBrokenReturnsFalseForA2xxStatus(): void
    {
        $httpClient = new MockHttpClient(fn (string $method, string $url, array $options) => new MockResponse('', ['http_code' => 200]));
        $client = new ContentQualityClient($httpClient);

        $this->assertFalse($client->isLinkBroken('https://example.com/pages/home/'));
    }

    public function testIsLinkBrokenReturnsTrueOnTransportFailure(): void
    {
        $httpClient = new MockHttpClient(fn (string $method, string $url, array $options) => new MockResponse('', ['error' => 'Connection refused']));
        $client = new ContentQualityClient($httpClient);

        $this->assertTrue($client->isLinkBroken('https://example.com/pages/home/'));
    }
}
