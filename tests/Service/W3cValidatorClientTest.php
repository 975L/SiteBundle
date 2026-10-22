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

    // The real case behind this: six errors on nine pages of one site for a correct stylesheet - a property the validator's profile predates, and two type verdicts on values made of variables it has just said it does not resolve
    public function testValidateCssSplitsOffTheErrorsTheValidatorCannotJudge(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse(json_encode([
                'cssvalidation' => [
                    'errors' => [
                        ['line' => 1385, 'type' => 'noexistence-at-all', 'message' => 'Property “corner-shape” doesn\'t exist : '],
                        ['line' => 1467, 'type' => 'incompatibletypes', 'message' => 'The types are incompatible'],
                        ['line' => 1469, 'type' => 'invalidtype', 'message' => 'Invalid type: “100% - var(--sheet-cards-row)”'],
                        ['line' => 12, 'type' => 'noexistence-at-all', 'message' => 'Property “colr” doesn\'t exist : '],
                    ],
                    'warnings' => [['line' => 3, 'message' => 'Due to their dynamic nature, CSS variables are currently not statically checked']],
                ],
            ]), ['http_code' => 200])
        );

        $result = new W3cValidatorClient($httpClient)->validateCss('https://example.com/pages/home/');

        // The typo stays an error: only the properties named one by one are excused
        $this->assertSame(['line 12: Property “colr” doesn\'t exist : '], $result['errors']);
        $this->assertCount(3, $result['benignErrors']);
    }

    // The real case behind this: 88 errors on every page of a site whose cards are sized in container units - the validator names neither the unit nor the value, so the line is read back from the stylesheet, once for all the errors it carries
    public function testValidateCssExcusesTheContainerQueryUnitsReadOnTheLineTheyAreOn(): void
    {
        $sheetRequests = 0;
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$sheetRequests): MockResponse {
                if (str_starts_with($url, 'https://example.com/css/site.css')) {
                    ++$sheetRequests;

                    return new MockResponse(".card {\n    container-type: inline-size;\n    border-radius: 2cqw;\n    text-shadow: 0 0.3cqw 0.8cqw black;\n    width: 10pz;\n}\n", ['http_code' => 200]);
                }

                $source = 'https://example.com/css/site.css?v=1';

                return new MockResponse(json_encode([
                    'cssvalidation' => [
                        'errors' => [
                            ['line' => 2, 'source' => $source, 'type' => 'noexistence-at-all', 'message' => 'Property “container-type” doesn\'t exist : '],
                            ['line' => 3, 'source' => $source, 'type' => 'generator.unrecognize', 'message' => 'Unknown dimension'],
                            ['line' => 4, 'source' => $source, 'type' => 'value', 'message' => '“0” is not a “text-shadow” value : '],
                            ['line' => 5, 'source' => $source, 'type' => 'generator.unrecognize', 'message' => 'Unknown dimension'],
                        ],
                        'warnings' => [],
                    ],
                ]), ['http_code' => 200]);
            }
        );

        $result = new W3cValidatorClient($httpClient)->validateCss('https://example.com/pages/home/');

        // The typo stays an error: only the units named one by one are excused
        $this->assertSame(['line 5: Unknown dimension'], $result['errors']);
        $this->assertCount(3, $result['benignErrors']);
        $this->assertSame(1, $sheetRequests);
    }

    // A stylesheet that cannot be read back excuses nothing: the validator's verdict stands
    public function testAnUnknownDimensionStaysAnErrorWhenTheStylesheetCannotBeRead(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => str_contains($url, 'site.css')
                ? new MockResponse('', ['http_code' => 404])
                : new MockResponse(json_encode([
                    'cssvalidation' => [
                        'errors' => [['line' => 3, 'source' => 'https://example.com/css/site.css', 'type' => 'generator.unrecognize', 'message' => 'Unknown dimension']],
                        'warnings' => [],
                    ],
                ]), ['http_code' => 200])
        );

        $result = new W3cValidatorClient($httpClient)->validateCss('https://example.com/pages/home/');

        $this->assertSame(['line 3: Unknown dimension'], $result['errors']);
        $this->assertSame([], $result['benignErrors']);
    }

    // Without the validator's admission about variables, a type verdict stays a verdict: a sheet with no variable writing calc(10px + 5) has a real error
    public function testATypeVerdictStaysAnErrorWhenTheValidatorDidNotDeclareVariablesUnchecked(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse(json_encode([
                'cssvalidation' => [
                    'errors' => [['line' => 8, 'type' => 'invalidtype', 'message' => 'Invalid type: “10px + 5”']],
                    'warnings' => [],
                ],
            ]), ['http_code' => 200])
        );

        $result = new W3cValidatorClient($httpClient)->validateCss('https://example.com/pages/home/');

        $this->assertSame(['line 8: Invalid type: “10px + 5”'], $result['errors']);
        $this->assertSame([], $result['benignErrors']);
    }

    // A page links several stylesheets and the validator names the one each message came from: the admission that variables were not resolved excuses the type verdicts of that sheet, and of no other
    public function testATypeVerdictStaysAnErrorOnASheetTheValidatorDidResolve(): void
    {
        $httpClient = new MockHttpClient(
            fn (string $method, string $url, array $options) => new MockResponse(json_encode([
                'cssvalidation' => [
                    'errors' => [
                        ['line' => 14, 'type' => 'invalidtype', 'source' => 'https://example.com/css/theme.css', 'message' => 'Invalid type: “100% - var(--gap)”'],
                        ['line' => 8, 'type' => 'invalidtype', 'source' => 'https://example.com/css/print.css', 'message' => 'Invalid type: “10px + 5”'],
                    ],
                    'warnings' => [['line' => 3, 'source' => 'https://example.com/css/theme.css', 'message' => 'Due to their dynamic nature, CSS variables are currently not statically checked']],
                ],
            ]), ['http_code' => 200])
        );

        $result = new W3cValidatorClient($httpClient)->validateCss('https://example.com/pages/home/');

        // print.css declared no variable, so its verdict is the validator's own and stands
        $this->assertSame(['line 8: Invalid type: “10px + 5”'], $result['errors']);
        $this->assertSame(['line 14: Invalid type: “100% - var(--gap)”'], $result['benignErrors']);
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
                        ['line' => 8, 'message' => 'The value “text” is deprecated'],
                    ],
                ],
            ]), ['http_code' => 200])
        );

        $client = new W3cValidatorClient($httpClient);
        $result = $client->validateCss('https://example.com/pages/home/');

        // A deprecated property is something to actually fix, unlike the others
        $this->assertSame(['line 7: The property “clip” is deprecated'], $result['warnings']);
        $this->assertCount(6, $result['benignWarnings']);
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

    // c975LHealthCheck is what HealthCheck::isProbe() matches on, and both endpoints have to be called under that same agent - requestCss() shipped without any header at all
    public function testBothRequestsCarryTheSameProbeUserAgent(): void
    {
        $userAgents = [];
        $httpClient = new MockHttpClient(
            function (string $method, string $url, array $options) use (&$userAgents): MockResponse {
                $userAgents[] = $options['normalized_headers']['user-agent'][0] ?? '';

                return new MockResponse('{}', ['http_code' => 200]);
            }
        );

        $client = new W3cValidatorClient($httpClient);
        $client->requestHtml('https://example.com/')->getStatusCode();
        $client->requestCss('https://example.com/')->getStatusCode();

        $this->assertCount(2, $userAgents);
        $this->assertSame($userAgents[0], $userAgents[1]);
        $this->assertStringContainsString('c975LHealthCheck', $userAgents[0]);
    }
}
