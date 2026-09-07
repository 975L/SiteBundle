<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Contracts\HttpClient\Exception\ClientExceptionInterface;
use Symfony\Contracts\HttpClient\Exception\HttpExceptionInterface;
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

// Thin wrapper around Google's PageSpeed Insights v5 API (https://developers.google.com/speed/docs/insights/v5/get-started) - one HTTP call returns Lighthouse's 4 category scores plus the "errors-in-console" audit, avoiding a Node/Lighthouse-CLI dependency in a PHP-only stack. Used by SitePageHealthCheckProvider, only ever from the c975l:health-check:run command (never a request-time controller - a PSI call can take 10-30s)
class PageSpeedInsightsClient
{
    private const string ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
    private const array CATEGORIES = ['performance', 'accessibility', 'best-practices', 'seo'];

    // Google's own explanation is one sentence ("Lighthouse returned error: ERRORED_DOCUMENT_REQUEST..."), but the error body also carries quota tables and help links, and this message is stored as a row's summary and read in a table cell. Cut rather than dropped: the first words are the ones that name the cause
    private const int MAX_REASON_LENGTH = 200;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // Fires the request and returns immediately without waiting for a response - Symfony's HttpClient transports (e.g. CurlHttpClient) multiplex every in-flight response, so a caller can request() several urls up front and read() them afterwards to run them concurrently. Do NOT do that across pages of one same site: PSI answers by loading the page itself, so N in-flight analyses means N simultaneous hits on that site, inflating the very TTFB being measured and dragging every score down (see SitePageHealthCheckProvider, which used to and no longer does). analyze() is the right entry point in all but very unusual cases
    public function request(string $url): ResponseInterface
    {
        try {
            return $this->httpClient->request('GET', self::buildUrl($url, $this->configService->get('healthcheck-pagespeed-api-key')), ['timeout' => 60]);
        } catch (\Throwable $e) {
            throw self::withoutUrl($e);
        }
    }

    // Blocks until the given in-flight response completes and parses it - same return shape/exceptions as analyze()
    public function read(ResponseInterface $response): array
    {
        $data = $this->getData($response, (bool) $this->configService->get('healthcheck-pagespeed-api-key'));

        return [
            'scores' => self::parseScores($data),
            'consoleErrors' => self::parseConsoleErrors($data),
            'raw' => $data['lighthouseResult']['categories'] ?? [],
        ];
    }

    // Convenience for a single-URL analysis - returns ['scores' => ['performance' => int, ...], 'consoleErrors' => string[], 'raw' => array] or throws on a network/API error
    public function analyze(string $url): array
    {
        return $this->read($this->request($url));
    }

    // Built by hand: Google rejects the "category[]=" shape HttpClient's 'query' option produces
    private static function buildUrl(string $url, mixed $apiKey): string
    {
        $query = http_build_query(['url' => $url, 'strategy' => 'mobile'] + ($apiKey ? ['key' => $apiKey] : []));
        foreach (self::CATEGORIES as $category) {
            $query .= '&category=' . $category;
        }

        return self::ENDPOINT . '?' . $query;
    }

    private function getData(ResponseInterface $response, bool $hasApiKey): array
    {
        try {
            return $response->toArray();
        } catch (ClientExceptionInterface $e) {
            // Without a key, PSI's anonymous quota is shared across every unauthenticated caller worldwide and is exhausted almost instantly - a 429 here almost always means exactly that, not a per-site rate limit
            if (429 === $e->getResponse()->getStatusCode() && !$hasApiKey) {
                throw new \RuntimeException('PageSpeed Insights rate limit reached (HTTP 429) - no API key configured, set "healthcheck-pagespeed-api-key" to get a much higher quota');
            }

            throw self::withoutUrl($e);
        } catch (\Throwable $e) {
            throw self::withoutUrl($e);
        }
    }

    // Google only accepts its API key in the query string, so HttpClient quotes the whole url - key included - in the message of every exception it raises. That message is what HealthCheckErrorRow stores as a row's summary AND details, which means the database, the dashboard, the CSV export and any status report leaving the site. None of those is a place for a credential, so the exception's own message is dropped and rebuilt here from the status code and the *response body*, which is Google's json error and carries no credential of its own - the request url is not echoed back in it. The original exception is deliberately NOT kept as "previous": Symfony's error logging unrolls the whole chain, which would put the key straight back in the logs
    private static function withoutUrl(\Throwable $e): \RuntimeException
    {
        if (!$e instanceof HttpExceptionInterface) {
            return new \RuntimeException('PageSpeed Insights could not be reached');
        }

        $reason = self::reason($e->getResponse());

        return new \RuntimeException(sprintf('PageSpeed Insights returned HTTP %d%s', $e->getResponse()->getStatusCode(), null !== $reason ? ' : ' . $reason : ''));
    }

    // What Google says went wrong, read from the error body it answers with. Worth the few lines: "HTTP 500" says only that the analysis did not happen, where the body says whether the page failed to load, whether Lighthouse never saw a first paint, or whether the day's quota is spent - three different things to go and do, and a bare status code sends the reader to look for a page defect in all three cases. Absent or unparseable, the status code alone still stands on its own
    private static function reason(ResponseInterface $response): ?string
    {
        try {
            $message = $response->toArray(false)['error']['message'] ?? null;
        } catch (\Throwable) {
            return null;
        }

        if (!\is_string($message) || '' === trim($message)) {
            return null;
        }

        // Belt and braces: the key is not in what Google answers, and it is not going in a row summary if that ever changes
        return mb_substr(trim((string) preg_replace('/key=[^&\s]+/', 'key=***', $message)), 0, self::MAX_REASON_LENGTH);
    }

    private static function parseScores(array $data): array
    {
        $scores = [];
        foreach (self::CATEGORIES as $category) {
            $score = $data['lighthouseResult']['categories'][$category]['score'] ?? null;
            $scores[$category] = null !== $score ? (int) round($score * 100) : null;
        }

        return $scores;
    }

    private static function parseConsoleErrors(array $data): array
    {
        $consoleErrors = [];
        foreach ($data['lighthouseResult']['audits']['errors-in-console']['details']['items'] ?? [] as $item) {
            $consoleErrors[] = $item['description'] ?? ($item['source'] ?? 'Unknown console error');
        }

        return $consoleErrors;
    }
}
