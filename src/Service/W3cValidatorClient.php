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

    // Blocks until the given in-flight response completes and parses it - same return shape as readHtml()
    public function readCss(ResponseInterface $response): array
    {
        $data = $response->toArray();
        $result = $data['cssvalidation'] ?? [];

        $errors = array_map(
            static fn (array $error) => sprintf('line %d: %s', $error['line'] ?? 0, is_array($error['message'] ?? null) ? implode(' ', $error['message']) : ($error['message'] ?? 'Unknown error')),
            $result['errors'] ?? [],
        );
        $warnings = array_map(
            static fn (array $warning) => sprintf('line %d: %s', $warning['line'] ?? 0, $warning['message'] ?? 'Unknown warning'),
            $result['warnings'] ?? [],
        );

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    // Convenience for a single-URL validation - returns the same shape as readCss(), or throws on a network/API error
    public function validateCss(string $url): array
    {
        return $this->readCss($this->requestCss($url));
    }
}
