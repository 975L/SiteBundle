<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Service\W3cValidatorClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpClient\MockHttpClient;
use Symfony\Component\HttpClient\Response\MockResponse;

class W3cValidatorClientTest extends TestCase
{
    public function testValidateHtmlSplitsErrorsAndWarnings(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse(json_encode([
                'messages' => [
                    ['type' => 'error', 'lastLine' => 12, 'message' => 'Unclosed element "div"'],
                    ['type' => 'info', 'subType' => 'warning', 'lastLine' => 30, 'message' => 'Consider using "alt" text'],
                    ['type' => 'info', 'lastLine' => 5, 'message' => 'Just an info, not a warning'],
                ],
            ]), ['http_code' => 200])
        );

        $client = new W3cValidatorClient($httpClient);
        $result = $client->validateHtml('https://example.com/pages/home/');

        $this->assertSame(['line 12: Unclosed element "div"'], $result['errors']);
        $this->assertSame(['line 30: Consider using "alt" text'], $result['warnings']);
    }

    public function testValidateHtmlReturnsEmptyArraysWhenNoMessages(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse(json_encode(['messages' => []]), ['http_code' => 200])
        );

        $client = new W3cValidatorClient($httpClient);
        $result = $client->validateHtml('https://example.com/pages/home/');

        $this->assertSame(['errors' => [], 'warnings' => []], $result);
    }

    public function testValidateCssSplitsErrorsAndWarnings(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse(json_encode([
                'cssvalidation' => [
                    'validity' => false,
                    'errors' => [['line' => 4, 'message' => 'Property "colour" doesn\'t exist']],
                    'warnings' => [['line' => 9, 'message' => 'Same colors for background and border']],
                ],
            ]), ['http_code' => 200])
        );

        $client = new W3cValidatorClient($httpClient);
        $result = $client->validateCss('https://example.com/pages/home/');

        $this->assertSame(['line 4: Property "colour" doesn\'t exist'], $result['errors']);
        $this->assertSame(['line 9: Same colors for background and border'], $result['warnings']);
    }

    public function testValidateCssHandlesAMultiPartMessageArray(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse(json_encode([
                'cssvalidation' => [
                    'validity' => false,
                    'errors' => [['line' => 4, 'message' => ['Value Error', 'colour is not a valid property']]],
                    'warnings' => [],
                ],
            ]), ['http_code' => 200])
        );

        $client = new W3cValidatorClient($httpClient);
        $result = $client->validateCss('https://example.com/pages/home/');

        $this->assertSame(['line 4: Value Error colour is not a valid property'], $result['errors']);
    }
}
