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

// Fetches robots.txt/sitemap-site.xml's own content for SeoFilesHealthCheckProvider - a plain GET (not HEAD), since both need their body inspected (robots.txt for a blanket "Disallow: /", the sitemap for well-formed XML), not just a status code
class SeoFilesClient
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
    ) {
    }

    // @return array{statusCode: int, content: string}
    public function fetch(string $url): array
    {
        $response = $this->httpClient->request('GET', $url, ['timeout' => 15]);

        return ['statusCode' => $response->getStatusCode(), 'content' => $response->getContent(false)];
    }
}
