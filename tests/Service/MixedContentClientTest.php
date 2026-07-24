<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Service\MixedContentClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class MixedContentClientTest extends TestCase
{
    public function testFindInsecureResourcesFindsAnInsecureImage(): void
    {
        $html = '<html><body><img src="http://example.com/photo.jpg"></body></html>';
        $client = new MixedContentClient(new MockHttpClient(fn () => new MockResponse($html)));

        $this->assertSame(['http://example.com/photo.jpg'], $client->findInsecureResources('https://example.com/pages/home/'));
    }

    public function testFindInsecureResourcesFindsAnInsecureStylesheet(): void
    {
        $html = '<html><head><link rel="stylesheet" href="http://example.com/style.css"></head></html>';
        $client = new MixedContentClient(new MockHttpClient(fn () => new MockResponse($html)));

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
        $client = new MixedContentClient(new MockHttpClient(fn () => new MockResponse($html)));

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
        $client = new MixedContentClient(new MockHttpClient(fn () => new MockResponse($html)));

        $this->assertSame([], $client->findInsecureResources('https://example.com/pages/home/'));
    }

    public function testFindInsecureResourcesDeduplicatesRepeatedResources(): void
    {
        $html = '<html><body><img src="http://example.com/photo.jpg"><img src="http://example.com/photo.jpg"></body></html>';
        $client = new MixedContentClient(new MockHttpClient(fn () => new MockResponse($html)));

        $this->assertSame(['http://example.com/photo.jpg'], $client->findInsecureResources('https://example.com/pages/home/'));
    }

    public function testFindInsecureResourcesReturnsEmptyArrayWithNoMatch(): void
    {
        $html = '<html><body><p>Nothing here.</p></body></html>';
        $client = new MixedContentClient(new MockHttpClient(fn () => new MockResponse($html)));

        $this->assertSame([], $client->findInsecureResources('https://example.com/pages/home/'));
    }
}
