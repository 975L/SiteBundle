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

// Parses a page's own rendered HTML (native DOMDocument/DOMXPath, no dependency) for the content-quality checks - meta description, H1, image alt text, internal links (for ContentQualityHealthCheckProvider's broken-link pass). Reading the actual rendered markup rather than the block data that produced it works regardless of which block kinds/theme a page uses
class ContentQualityClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    // Fires the request and returns immediately without waiting for a response - Symfony's HttpClient transports multiplex every in-flight response, so a caller analyzing many pages/links (ContentQualityHealthCheckProvider) can request()/requestLinkCheck() all of them up front and read()/readLinkCheck() them afterwards to run them concurrently instead of paying each timeout serially
    public function request(string $url): ResponseInterface
    {
        return $this->httpClient->request('GET', $url, ['timeout' => 30]);
    }

    // Blocks until the given in-flight response completes and parses it - $url is the same one passed to request(), needed again here to resolve internal links against its own host. Returns ['hasDescription' => bool, 'hasH1' => bool, 'imagesWithoutAlt' => int, 'internalLinks' => string[] (deduped, absolute, same-host only)]
    public function read(ResponseInterface $response, string $url): array
    {
        $xpath = $this->buildXPath($response->getContent());
        $host = parse_url($url, \PHP_URL_HOST);

        $description = $xpath->query('//meta[@name="description"]/@content')->item(0)?->nodeValue;

        return [
            'hasDescription' => '' !== trim((string) $description),
            'hasH1' => $xpath->query('//h1')->length > 0,
            'imagesWithoutAlt' => $xpath->query('//img[not(@alt) or @alt=""]')->length,
            'internalLinks' => $this->extractInternalLinks($xpath, $url, $host),
        ];
    }

    // Convenience for a single-URL analysis - returns the same shape as read(), or throws on a network/API error
    public function analyze(string $url): array
    {
        return $this->read($this->request($url), $url);
    }

    // A HEAD request is enough to know if a link resolves
    public function requestLinkCheck(string $url): ResponseInterface
    {
        return $this->httpClient->request('HEAD', $url, ['timeout' => 15]);
    }

    // Falls back to true (broken) on any transport failure (DNS, timeout, connection refused), which is exactly what "broken" means for a visitor following that link
    public function readLinkCheck(ResponseInterface $response): bool
    {
        try {
            return $response->getStatusCode() >= 400;
        } catch (\Throwable) {
            return true;
        }
    }

    // Convenience for a single-URL check - returns the same value as readLinkCheck(), also catching a synchronous failure from request() itself
    public function isLinkBroken(string $url): bool
    {
        try {
            return $this->readLinkCheck($this->requestLinkCheck($url));
        } catch (\Throwable) {
            return true;
        }
    }

    private function buildXPath(string $html): \DOMXPath
    {
        libxml_use_internal_errors(true);
        $dom = new \DOMDocument();
        // Forces UTF-8 interpretation regardless of the page's own <meta charset> (or lack thereof) - DOMDocument defaults to ISO-8859-1 otherwise, mangling accented characters
        $dom->loadHTML('<?xml encoding="utf-8">' . $html, \LIBXML_NOERROR | \LIBXML_NOWARNING);
        libxml_clear_errors();

        return new \DOMXPath($dom);
    }

    // Root-relative ("/pages/contact/") and same-host absolute links only - external links, anchors, mailto:/tel:/javascript: are not this site's problem to fix
    private function extractInternalLinks(\DOMXPath $xpath, string $pageUrl, ?string $host): array
    {
        $scheme = parse_url($pageUrl, \PHP_URL_SCHEME);
        $links = [];

        foreach ($xpath->query('//a[@href]') as $anchor) {
            $href = trim($anchor->getAttribute('href'));
            if ('' === $href || str_starts_with($href, '#') || preg_match('/^(mailto|tel|javascript):/i', $href)) {
                continue;
            }

            if (str_starts_with($href, '/') && !str_starts_with($href, '//')) {
                $links[] = $scheme . '://' . $host . $href;
            } elseif (parse_url($href, \PHP_URL_HOST) === $host) {
                $links[] = $href;
            }
        }

        return array_values(array_unique($links));
    }
}
