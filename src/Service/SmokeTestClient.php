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

// Fires the requests behind c975l:site:smoke-test - a "did the deployment break anything obvious" pass, so it only ever reads the status code and never analyzes content: judging a page's quality is what the HealthCheckProviderInterface implementations do, on their own schedule. Every request is fired before any status is read, letting the transport multiplex them - measured on a 21-page site, 53 urls take ~1.5s concurrently against ~10.7s serially, which is what makes checking every published page affordable inside a deployment
class SmokeTestClient
{
    // Any href/src pointing at a .css/.js file, cache-busting query string included. AssetMapper's filenames are hashed (app-EiPntxm.css) and so impossible to declare anywhere: reading them back out of the rendered HTML is the only way to check that asset-map:compile and StylesheetCacheWarmer both ran, and ran in the right order. A lightweight regex rather than a full DOM parse, same reasoning as MixedContentClient
    private const ASSET_PATTERN = '/\b(?:href|src)\s*=\s*["\']([^"\']+\.(?:css|js)(?:\?[^"\']*)?)["\']/i';

    private const TIMEOUT = 30;

    // A deployment purges var/cache/prod, so the very first request rebuilds it and can legitimately be slow - a plain timeout here would report a healthy site as broken
    private const FIRST_BYTE_TIMEOUT = 60;

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    // [url => status code], in the order given. 0 means no answer at all (DNS, TLS, connection refused): the caller reports it like any other non-200 rather than letting one dead url abort the whole run
    public function check(array $urls): array
    {
        $responses = [];
        foreach ($urls as $url) {
            try {
                $responses[$url] = $this->httpClient->request('GET', $url, [
                    'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; c975l-smoke-test)'],
                    'timeout' => self::FIRST_BYTE_TIMEOUT,
                    // Nothing here reads the body, so there's no reason to hold each asset in memory - a site's whole js bundle would otherwise be buffered just to learn it answered 200
                    'buffer' => false,
                ]);
            } catch (\Throwable) {
                $responses[$url] = null;
            }
        }

        $statuses = [];
        foreach ($responses as $url => $response) {
            if (null === $response) {
                $statuses[$url] = 0;
                continue;
            }

            try {
                $statuses[$url] = $response->getStatusCode();
            } catch (\Throwable) {
                $statuses[$url] = 0;
            }
        }

        return $statuses;
    }

    // Distinct absolute urls of every css/js asset the page's markup references, relative ones resolved against $baseUrl. Empty if the page can't be fetched at all - check() is what reports that url as broken, this doesn't need to say it twice
    public function findAssets(string $url, string $baseUrl): array
    {
        try {
            $html = $this->httpClient->request('GET', $url, [
                'headers' => ['User-Agent' => 'Mozilla/5.0 (compatible; c975l-smoke-test)'],
                'timeout' => self::TIMEOUT,
            ])->getContent();
        } catch (\Throwable) {
            return [];
        }

        if (!preg_match_all(self::ASSET_PATTERN, $html, $matches)) {
            return [];
        }

        $assets = [];
        foreach ($matches[1] as $asset) {
            $assets[] = str_starts_with($asset, 'http')
                ? $asset
                : rtrim($baseUrl, '/') . '/' . ltrim($asset, '/');
        }

        return array_values(array_unique($assets));
    }
}
