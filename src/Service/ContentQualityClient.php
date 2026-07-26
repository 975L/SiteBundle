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
    // A missing alt attribute is always an error, but an explicitly empty one (alt="") is the *correct* way to mark a decorative image - it only counts when nothing marks it as such: no aria-hidden, no role="presentation"/"none", and no enclosing link/button already carrying its own accessible name (a share button's icon, a logo inside a labelled link). Flagging those would leave a page in warning forever, since there is nothing to fix
    private const DECORATIVE_IMAGE = '@aria-hidden="true" or @role="presentation" or @role="none" or ancestor::*[self::a or self::button][@aria-label or @aria-labelledby]';

    // Verdicts returned by readLinkCheck()/checkLink(). LINK_UNKNOWN is deliberately not LINK_BROKEN: a timeout, a refused connection or a server that won't answer HEAD says something about the run, not about the link, and a health check reporting a live page as dead is worse than reporting nothing at all
    public const LINK_OK = 'ok';
    public const LINK_BROKEN = 'broken';
    public const LINK_UNKNOWN = 'unknown';

    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    // Fires the request and returns immediately without waiting for a response - Symfony's HttpClient transports multiplex every in-flight response, so a caller analyzing many pages/links (ContentQualityHealthCheckProvider) can request()/requestLinkCheck() all of them up front and read()/readLinkCheck() them afterwards to run them concurrently instead of paying each timeout serially
    public function request(string $url): ResponseInterface
    {
        return $this->httpClient->request('GET', $url, ['timeout' => 30]);
    }

    // Blocks until the given in-flight response completes and parses it - $url is the same one passed to request(), needed again here to resolve internal links against its own host. Returns ['hasDescription' => bool, 'hasH1' => bool, 'imagesWithoutAlt' => string[] (each offending img's src), 'internalLinks' => string[] (deduped, absolute, same-host only), 'linkTexts' => array<string, string> (each internal link's anchor text)]
    public function read(ResponseInterface $response, string $url): array
    {
        $xpath = $this->buildXPath($response->getContent());
        $host = parse_url($url, \PHP_URL_HOST);

        $description = $xpath->query('//meta[@name="description"]/@content')->item(0)?->nodeValue;
        ['links' => $internalLinks, 'texts' => $linkTexts] = $this->extractInternalLinks($xpath, $url, $host);

        return [
            'hasDescription' => '' !== trim((string) $description),
            'hasH1' => $xpath->query('//h1')->length > 0,
            'imagesWithoutAlt' => $this->extractImagesWithoutAlt($xpath),
            'internalLinks' => $internalLinks,
            'linkTexts' => $linkTexts,
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

    // Second pass for a link the HEAD couldn't conclude on - a fair share of servers answer 405/501 to HEAD, or drop it altogether, on urls that serve perfectly well in GET
    public function requestLinkCheckFallback(string $url): ResponseInterface
    {
        return $this->httpClient->request('GET', $url, ['timeout' => 15]);
    }

    // Only a conclusive >= 400 answer means broken. A transport failure (DNS, timeout, connection refused) yields LINK_UNKNOWN, and so does a 405/501, which says the server refuses the HEAD *method* rather than the url - both are worth a requestLinkCheckFallback() retry before anything is called broken
    public function readLinkCheck(ResponseInterface $response): string
    {
        try {
            $status = $response->getStatusCode();
        } catch (\Throwable) {
            return self::LINK_UNKNOWN;
        }

        return match (true) {
            405 === $status, 501 === $status => self::LINK_UNKNOWN,
            $status >= 400 => self::LINK_BROKEN,
            default => self::LINK_OK,
        };
    }

    // Convenience for a single-URL check, HEAD then GET - returns one of the LINK_* verdicts, catching a synchronous failure from request() itself the same way as a failed transfer
    public function checkLink(string $url): string
    {
        $verdict = $this->readLinkCheckSafely(fn (): ResponseInterface => $this->requestLinkCheck($url));

        return self::LINK_UNKNOWN === $verdict
            ? $this->readLinkCheckSafely(fn (): ResponseInterface => $this->requestLinkCheckFallback($url))
            : $verdict;
    }

    // True only on a conclusive LINK_BROKEN - an unreachable host is not reported as a broken link
    public function isLinkBroken(string $url): bool
    {
        return self::LINK_BROKEN === $this->checkLink($url);
    }

    private function readLinkCheckSafely(callable $request): string
    {
        try {
            return $this->readLinkCheck($request());
        } catch (\Throwable) {
            return self::LINK_UNKNOWN;
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

    // Each offending image's own src rather than just how many there are, so the Health check panel can list them one by one (and SiteBundle's PageBlockLocator trace each one back to the block holding it). Deduped: the same image used twice on a page is a single alt text to write, not two
    private function extractImagesWithoutAlt(\DOMXPath $xpath): array
    {
        $sources = [];

        foreach ($xpath->query('//img[not(@alt) or (@alt="" and not(' . self::DECORATIVE_IMAGE . '))]') as $image) {
            $src = trim($image->getAttribute('src'));
            if ('' !== $src) {
                $sources[$src] = true;
            }
        }

        return array_keys($sources);
    }

    // Root-relative ("/pages/contact/") and same-host absolute links only - external links, anchors, mailto:/tel:/javascript: are not this site's problem to fix. Each link's anchor text is kept alongside it ('texts'), so a broken link can be listed by what the visitor actually clicks on rather than by its url alone - first occurrence wins, the same url linked twice with two different labels only needs fixing once
    private function extractInternalLinks(\DOMXPath $xpath, string $pageUrl, ?string $host): array
    {
        $scheme = parse_url($pageUrl, \PHP_URL_SCHEME);
        $links = [];
        $texts = [];

        foreach ($xpath->query('//a[@href]') as $anchor) {
            $href = trim($anchor->getAttribute('href'));
            if ('' === $href || str_starts_with($href, '#') || preg_match('/^(mailto|tel|javascript):/i', $href)) {
                continue;
            }

            if (str_starts_with($href, '/') && !str_starts_with($href, '//')) {
                $link = $scheme . '://' . $host . $href;
            } elseif (parse_url($href, \PHP_URL_HOST) === $host) {
                $link = $href;
            } else {
                continue;
            }

            $links[$link] = true;
            $texts[$link] ??= $this->anchorText($anchor);
        }

        return ['links' => array_keys($links), 'texts' => array_filter($texts)];
    }

    // An image-only link has no text of its own - its image's alt is the closest thing to a label, and an empty string when it has none either (filtered out by the caller)
    private function anchorText(\DOMNode $anchor): string
    {
        $text = trim(preg_replace('/\s+/', ' ', $anchor->textContent));
        if ('' !== $text) {
            return $text;
        }

        $image = (new \DOMXPath($anchor->ownerDocument))->query('.//img[@alt]', $anchor)->item(0);

        return $image instanceof \DOMElement ? trim($image->getAttribute('alt')) : '';
    }
}
