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

    // The Nu checker splits a message the same way the CSS one does, and used to render it as "line 12: Array"
    public function testValidateHtmlJoinsAMessageReturnedAsAnArray(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse(json_encode([
                'messages' => [
                    ['type' => 'error', 'lastLine' => 12, 'message' => ['Unclosed element', '"div"']],
                    ['type' => 'info', 'subType' => 'warning', 'lastLine' => 30, 'message' => ['Consider using', '"alt" text']],
                ],
            ]), ['http_code' => 200])
        );

        $client = new W3cValidatorClient($httpClient);
        $result = $client->validateHtml('https://example.com/pages/home/');

        $this->assertSame(['line 12: Unclosed element "div"'], $result['errors']);
        $this->assertSame(['line 30: Consider using "alt" text'], $result['warnings']);
    }

    // Neither the line number nor the wording is guaranteed to be there - a message missing both must still read as a line, not crash the whole report
    public function testValidateHtmlFallsBackWhenAMessageCarriesNoLineOrText(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse(json_encode([
                'messages' => [['type' => 'error']],
            ]), ['http_code' => 200])
        );

        $client = new W3cValidatorClient($httpClient);
        $result = $client->validateHtml('https://example.com/pages/home/');

        $this->assertSame(['line 0: Unknown error'], $result['errors']);
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
        $this->assertSame([], $result['benignWarnings']);
    }

    // The validator sometimes splits a message into several parts - both errors and warnings have to join them, or the row reads "line 9: Array"
    public function testValidateCssJoinsAMessageReturnedAsAnArray(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse(json_encode([
                'cssvalidation' => [
                    'validity' => false,
                    'errors' => [['line' => 4, 'message' => ['Property', '"colour"', 'doesn\'t exist']]],
                    'warnings' => [['line' => 9, 'message' => ['Same colors for', 'background and border']]],
                ],
            ]), ['http_code' => 200])
        );

        $client = new W3cValidatorClient($httpClient);
        $result = $client->validateCss('https://example.com/pages/home/');

        $this->assertSame(['line 4: Property "colour" doesn\'t exist'], $result['errors']);
        $this->assertSame(['line 9: Same colors for background and border'], $result['warnings']);
    }

    // Warnings about constructs the validator's own CSS3 profile predates are split off, not dropped - the Health check shows the same total as the W3C report, only the status follows the actionable count
    public function testValidateCssSplitsOffBenignWarnings(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse(json_encode([
                'cssvalidation' => [
                    'validity' => true,
                    'errors' => [],
                    'warnings' => [
                        ['line' => 2, 'message' => 'Due to their dynamic nature, CSS variables are currently not statically checked'],
                        ['line' => 3, 'message' => '“-webkit-line-clamp” is a vendor extension'],
                        ['line' => 4, 'message' => '“::-webkit-file-upload-button” is a vendor extended pseudo-element'],
                        ['line' => 5, 'message' => '“:-moz-focusring” is a vendor extended pseudo-class'],
                        ['line' => 6, 'message' => '“auto” is not defined by any specification as an allowed value for “pointer-events”, but is supported in multiple browsers'],
                        ['line' => 7, 'message' => 'The property “clip” is deprecated'],
                    ],
                ],
            ]), ['http_code' => 200])
        );

        $client = new W3cValidatorClient($httpClient);
        $result = $client->validateCss('https://example.com/pages/home/');

        // A deprecated property is something to actually fix, unlike the five above it
        $this->assertSame(['line 7: The property “clip” is deprecated'], $result['warnings']);
        $this->assertCount(5, $result['benignWarnings']);
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
