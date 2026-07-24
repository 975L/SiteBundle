<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Service\SeoFilesClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\Exception\TransportException;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class SeoFilesClientTest extends TestCase
{
    public function testFetchReturnsStatusCodeAndContent(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse("User-agent: *\nDisallow:", ['http_code' => 200])
        );

        $client = new SeoFilesClient($httpClient);
        $file = $client->fetch('https://example.com/robots.txt');

        $this->assertSame(200, $file['statusCode']);
        $this->assertSame("User-agent: *\nDisallow:", $file['content']);
    }

    public function testFetchDoesNotThrowOnANonSuccessStatusCode(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse('Not Found', ['http_code' => 404])
        );

        $client = new SeoFilesClient($httpClient);
        $file = $client->fetch('https://example.com/robots.txt');

        $this->assertSame(404, $file['statusCode']);
    }

    public function testFetchPropagatesTransportExceptions(): void
    {
        $this->expectException(TransportException::class);

        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse('', ['error' => 'Connection refused'])
        );

        $client = new SeoFilesClient($httpClient);
        $client->fetch('https://example.com/robots.txt');
    }
}
