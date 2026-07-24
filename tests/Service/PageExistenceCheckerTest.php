<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Service\PageExistenceChecker;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class PageExistenceCheckerTest extends TestCase
{
    public function testExistsReturnsTrueOn2xx(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse('', ['http_code' => 200])
        );

        $checker = new PageExistenceChecker($httpClient);

        $this->assertTrue($checker->exists('https://example.com/pages/home/'));
    }

    public function testExistsReturnsFalseOn404(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse('', ['http_code' => 404])
        );

        $checker = new PageExistenceChecker($httpClient);

        $this->assertFalse($checker->exists('https://example.com/pages/missing/'));
    }

    public function testExistsReturnsFalseWhenTheRequestThrows(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => throw new \RuntimeException('DNS failure')
        );

        $checker = new PageExistenceChecker($httpClient);

        $this->assertFalse($checker->exists('https://unresolvable.example/'));
    }
}
