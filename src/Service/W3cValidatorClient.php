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
    private const string HTML_ENDPOINT = 'https://validator.w3.org/nu/';
    private const string CSS_ENDPOINT = 'https://jigsaw.w3.org/css-validator/validator';

    // Warnings the CSS validator's own profile predates; returned in full, only counted apart so custom properties don't leave the row permanently orange
    private const array BENIGN_CSS_WARNING_PATTERNS = [
        // "Due to their dynamic nature, CSS variables are currently not statically checked" - custom properties are a W3C Recommendation the validator cannot resolve statically, not a defect
        'not statically checked',
        // "-webkit-line-clamp is a vendor extension" - no standard equivalent that validates (the standard "line-clamp" is rejected as an error by this same profile)
        'is a vendor extension',
        // "::-webkit-file-upload-button is a vendor extended pseudo-element", ":-moz-focusring is a vendor extended pseudo-class"
        'vendor extended pseudo-',
        // "auto is not defined by any specification as an allowed value for pointer-events, but is supported in multiple browsers" - the profile predates the spec that defines it
        'but is supported in multiple browsers',
    ];

    // The warning by which the validator declares it did not resolve this stylesheet's custom properties (it is one of the benign ones above). Its own admission, and the only evidence that makes a type verdict on a var() expression worth discarding
    private const string CSS_VARIABLES_UNCHECKED = 'not statically checked';

    // What the validator answers for a property its profile does not carry, and the two verdicts it returns for a value it could not type
    private const string TYPE_PROPERTY_UNKNOWN = 'noexistence-at-all';
    private const array CSS_TYPE_VERDICTS = ['incompatibletypes', 'invalidtype'];

    // Properties that exist in a CSS level the css3svg profile predates, so "doesn't exist" says something about the validator and not about the stylesheet. Named one by one, never inferred: an unknown property is a typo far more often than it is the future, and only the ones written here are excused
    private const array CSS_PROPERTIES_NEWER_THAN_PROFILE = [
        // CSS Borders and Box Decorations Level 4, shipped in Chrome 139. Used with border-radius to round or notch a corner, and correctly guarded by @supports on the sites using it - a guard this validator does not read
        'corner-shape',
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
        $errors = [];
        $warnings = [];
        foreach ($response->toArray()['messages'] ?? [] as $message) {
            $text = self::messageLine($message, 'lastLine', 'Unknown error');
            if ('error' === ($message['type'] ?? null)) {
                $errors[] = $text;
            } elseif ('warning' === ($message['subType'] ?? null)) {
                $warnings[] = $text;
            }
        }

        return ['errors' => $errors, 'warnings' => $warnings];
    }

    // "line N: text", from the validator's own line number and wording - both validators sometimes return a message split into several parts, joined back here rather than rendering as "Array". The two don't name their line the same way, hence $lineKey ("lastLine" for the Nu checker, "line" for the CSS one)
    private static function messageLine(array $message, string $lineKey, string $fallback): string
    {
        $text = $message['message'] ?? $fallback;

        return sprintf('line %d: %s', $message[$lineKey] ?? 0, is_array($text) ? implode(' ', $text) : $text);
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
        $result = $response->toArray()['cssvalidation'] ?? [];

        $warnings = [];
        $benignWarnings = [];
        // Indexed by the stylesheet the validator names, not kept as one flag for the whole page: a page links several sheets, and the validator saying it did not resolve the custom properties of one of them says nothing about the others - a single flag had one variable-using sheet excuse every type verdict raised on all the rest
        $uncheckedSources = [];
        foreach ($result['warnings'] ?? [] as $warning) {
            $text = self::messageLine($warning, 'line', 'Unknown warning');
            if ($this->isBenignCssWarning($text)) {
                $benignWarnings[] = $text;
                // Read off the benign ones, where this warning always lands: its own wording is in BENIGN_CSS_WARNING_PATTERNS
                if (str_contains($text, self::CSS_VARIABLES_UNCHECKED)) {
                    $uncheckedSources[$warning['source'] ?? ''] = true;
                }

                continue;
            }

            $warnings[] = $text;
        }

        $errors = [];
        $benignErrors = [];
        foreach ($result['errors'] ?? [] as $error) {
            $text = self::messageLine($error, 'line', 'Unknown error');
            if ($this->isBenignCssError($error, $uncheckedSources)) {
                $benignErrors[] = $text;
                continue;
            }

            $errors[] = $text;
        }

        return ['errors' => $errors, 'benignErrors' => $benignErrors, 'warnings' => $warnings, 'benignWarnings' => $benignWarnings];
    }

    // The validator's messages are always English (no Accept-Language is sent), so matching on their own wording is stable
    private function isBenignCssWarning(string $message): bool
    {
        return array_any(self::BENIGN_CSS_WARNING_PATTERNS, fn ($pattern) => str_contains($message, $pattern));
    }

    // An error the validator's own profile is not in a position to raise, matched on the machine-readable "type" rather than on wording: a property this profile predates is excused by name only, so a typo stays an error, and a type verdict is excused only on a stylesheet the validator has just declared it did not resolve the variables of
    /** @param array<string, true> $uncheckedSources stylesheets the validator declared unresolved, keyed by the "source" it reports */
    private function isBenignCssError(array $error, array $uncheckedSources): bool
    {
        $message = $error['message'] ?? '';
        $message = \is_array($message) ? implode(' ', $message) : (string) $message;

        if (self::TYPE_PROPERTY_UNKNOWN === ($error['type'] ?? null)) {
            return array_any(
                self::CSS_PROPERTIES_NEWER_THAN_PROFILE,
                static fn (string $property): bool => str_contains($message, sprintf('“%s”', $property)),
            );
        }

        return isset($uncheckedSources[$error['source'] ?? '']) && \in_array($error['type'] ?? null, self::CSS_TYPE_VERDICTS, true);
    }

    // Convenience for a single-URL validation - returns the same shape as readCss(), or throws on a network/API error
    public function validateCss(string $url): array
    {
        return $this->readCss($this->requestCss($url));
    }
}
