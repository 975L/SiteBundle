<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

// Thin wrapper around the two free public W3C validators - the Nu HTML Checker (https://validator.w3.org/nu/) and the CSS Validator (https://jigsaw.w3.org/css-validator/) - both called with a plain "check this url" query, no API key. Used by W3cHtmlHealthCheckProvider/W3cCssHealthCheckProvider, only ever from the c975l:health-check:run command
class W3cValidatorClient
{
    private const HTML_ENDPOINT = 'https://validator.w3.org/nu/';
    private const CSS_ENDPOINT = 'https://jigsaw.w3.org/css-validator/validator';

    // Warnings the CSS validator raises about constructs that are correct and deliberate rather than defects to
    // fix - its own CSS3 profile simply predates them. They are returned in full (readCss()'s "benignWarnings"),
    // only counted apart from the actionable ones: the Health check summary still shows the same warning total as
    // the W3C report it links to, and only the row's status follows the actionable count. Without this, a
    // stylesheet built on custom properties (one warning per var() usage, ~65 on a c975L site) leaves the
    // "w3c-css" row permanently orange, which is a light nobody looks at any more
    private const BENIGN_CSS_WARNING_PATTERNS = [
        // "Due to their dynamic nature, CSS variables are currently not statically checked" - custom properties are a W3C Recommendation the validator cannot resolve statically, not a defect
        'not statically checked',
        // "-webkit-line-clamp is a vendor extension" - no standard equivalent that validates (the standard "line-clamp" is rejected as an error by this same profile)
        'is a vendor extension',
        // "::-webkit-file-upload-button is a vendor extended pseudo-element", ":-moz-focusring is a vendor extended pseudo-class"
        'vendor extended pseudo-',
        // "auto is not defined by any specification as an allowed value for pointer-events, but is supported in multiple browsers" - the profile predates the spec that defines it
        'but is supported in multiple browsers',
    ];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    // Fires the request and returns immediately without waiting for a response - Symfony's HttpClient transports multiplex every in-flight response, so a caller validating many pages (AbstractW3cValidationHealthCheckProvider) can requestHtml()/requestCss() all of them up front and readHtml()/readCss() them afterwards to run them concurrently instead of paying each ~60s timeout serially
    public function requestHtml(string $url): ResponseInterface
    {
        return $this->httpClient->request('GET', self::HTML_ENDPOINT, [
            'query' => ['doc' => $url, 'out' => 'json'],
            'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; c975l-health-check)'],
            'timeout' => 60,
        ]);
    }

    // Blocks until the given in-flight response completes and parses it - returns ['errors' => string[], 'warnings' => string[]], one entry per message, "line N: text" for easy reading in the summary/details
    public function readHtml(ResponseInterface $response): array
    {
        $data = $response->toArray();

        $errors = [];
        $warnings = [];
        foreach ($data['messages'] ?? [] as $message) {
            $text = sprintf('line %d: %s', $message['lastLine'] ?? 0, $message['message'] ?? 'Unknown error');
            if ('error' === ($message['type'] ?? null)) {
                $errors[] = $text;
            } elseif ('warning' === ($message['subType'] ?? null)) {
                $warnings[] = $text;
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    // Convenience for a single-URL validation - returns the same shape as readHtml(), or throws on a network/API error
    public function validateHtml(string $url): array
    {
        return $this->readHtml($this->requestHtml($url));
    }

    // The CSS validator only accepts a fetchable url (not raw CSS content here) - checks every stylesheet linked from the page
    public function requestCss(string $url): ResponseInterface
    {
        return $this->httpClient->request('GET', self::CSS_ENDPOINT, [
            'query' => ['uri' => $url, 'output' => 'json', 'profile' => 'css3svg'],
            'timeout' => 60,
        ]);
    }

    // Blocks until the given in-flight response completes and parses it - same return shape as readHtml(), plus 'benignWarnings' (string[]) holding the warnings split off by BENIGN_CSS_WARNING_PATTERNS. Nothing is dropped: 'warnings' + 'benignWarnings' is exactly what the W3C report shows
    public function readCss(ResponseInterface $response): array
    {
        $data = $response->toArray();
        $result = $data['cssvalidation'] ?? [];

        $errors = array_map(
            static fn (array $error) => sprintf('line %d: %s', $error['line'] ?? 0, is_array($error['message'] ?? null) ? implode(' ', $error['message']) : ($error['message'] ?? 'Unknown error')),
            $result['errors'] ?? [],
        );

        $warnings = [];
        $benignWarnings = [];
        foreach ($result['warnings'] ?? [] as $warning) {
            // Same guard as the errors above: the validator sometimes returns a message split into several parts, which would otherwise render as "Array"
            $text = sprintf('line %d: %s', $warning['line'] ?? 0, is_array($warning['message'] ?? null) ? implode(' ', $warning['message']) : ($warning['message'] ?? 'Unknown warning'));
            if ($this->isBenignCssWarning($text)) {
                $benignWarnings[] = $text;
                continue;
            }

            $warnings[] = $text;
        }

        return ['errors' => $errors, 'warnings' => $warnings, 'benignWarnings' => $benignWarnings];
    }

    // The validator's messages are always English (no Accept-Language is sent), so matching on their own wording is stable
    private function isBenignCssWarning(string $message): bool
    {
        foreach (self::BENIGN_CSS_WARNING_PATTERNS as $pattern) {
            if (str_contains($message, $pattern)) {
                return true;
            }
        }

        return false;
    }

    // Convenience for a single-URL validation - returns the same shape as readCss(), or throws on a network/API error
    public function validateCss(string $url): array
    {
        return $this->readCss($this->requestCss($url));
    }
}
