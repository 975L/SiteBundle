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
use Symfony\Contracts\HttpClient\HttpClientInterface;
use Symfony\Contracts\HttpClient\ResponseInterface;

// Thin wrapper around Google's PageSpeed Insights v5 API (https://developers.google.com/speed/docs/insights/v5/get-started) - one HTTP call returns Lighthouse's 4 category scores plus the "errors-in-console" audit, avoiding a Node/Lighthouse-CLI dependency in a PHP-only stack. Used by SitePageHealthCheckProvider, only ever from the c975l:health-check:run command (never a request-time controller - a PSI call can take 10-30s)
class PageSpeedInsightsClient
{
    private const ENDPOINT = 'https://www.googleapis.com/pagespeedonline/v5/runPagespeed';
    private const CATEGORIES = ['performance', 'accessibility', 'best-practices', 'seo'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly ConfigServiceInterface $configService,
    ) {
    }

    // Fires the request and returns immediately without waiting for a response - Symfony's HttpClient transports (e.g. CurlHttpClient) multiplex every in-flight response, so a caller analyzing many pages (SitePageHealthCheckProvider) can request() all of them up front and read() them afterwards to run them concurrently instead of paying each ~60s timeout serially
    public function request(string $url): ResponseInterface
    {
        return $this->httpClient->request('GET', self::buildUrl($url, $this->configService->get('healthcheck-pagespeed-api-key')), ['timeout' => 60]);
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

    // Repeated "category" query params - Google's API doesn't accept the "category[]="/"category[0]=" shape
    // Symfony HttpClient's own 'query' option would produce for an array value, so the query string is built by hand instead
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
                throw new \RuntimeException('PageSpeed Insights rate limit reached (HTTP 429) - no API key configured, set "healthcheck-pagespeed-api-key" to get a much higher quota', previous: $e);
            }

            throw $e;
        }
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
