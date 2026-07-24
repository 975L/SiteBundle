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

// Scans a page's rendered HTML for resources fetched over plain http:// - exactly what a browser's own "mixed content" warning flags on an https:// page. A lightweight regex over the handful of resource-bearing attributes that matter, not a full DOM parse - no extra dependency needed for this. Never matches <a href>: a plain hyperlink isn't fetched by the browser, so it's never "mixed content"
class MixedContentClient
{
    private const RESOURCE_TAG_PATTERN = '/<(?:img|script|iframe|source|audio|video)\b[^>]*\bsrc\s*=\s*["\'](http:\/\/[^"\']+)["\']/i';
    private const STYLESHEET_PATTERN = '/<link\b[^>]*\brel\s*=\s*["\']stylesheet["\'][^>]*\bhref\s*=\s*["\'](http:\/\/[^"\']+)["\']/i';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    // Distinct http:// resource urls found in the page's own markup
    public function findInsecureResources(string $url): array
    {
        $html = $this->httpClient->request('GET', $url, ['timeout' => 30])->getContent();

        $found = [];
        if (preg_match_all(self::RESOURCE_TAG_PATTERN, $html, $matches)) {
            $found = [...$found, ...$matches[1]];
        }
        if (preg_match_all(self::STYLESHEET_PATTERN, $html, $matches)) {
            $found = [...$found, ...$matches[1]];
        }

        return array_values(array_unique($found));
    }
}
