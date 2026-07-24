<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\HealthCheckProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Service\SeoFilesClient;
use Symfony\Contracts\Translation\TranslatorInterface;

// Checks robots.txt and sitemap-site.xml (see SitemapCreateCommand) are actually reachable and sane - both are silent, easy-to-forget deployment steps (eg. a fresh environment never running c975l:site:sitemaps:create, or an app-level robots.txt left blocking everything from a staging config). The robots.txt "blocks everything" check is a heuristic, not a full parser - it only catches the single most damaging misconfiguration (a global "Disallow: /" under "User-agent: *"), not every possible robots.txt edge case. Also checks sitemap-index.xml (only produced by the app-level scaffolded multi-sitemap command) and every child sitemap it references, when one is deployed
class SeoFilesHealthCheckProvider implements HealthCheckProviderInterface
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly SeoFilesClient $seoFilesClient,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getKind(): string
    {
        return 'seo-files';
    }

    public function runChecks(): array
    {
        $siteUrl = $this->configService->get('site-url');
        if (!$siteUrl) {
            return [];
        }

        $rows = [
            $this->checkRobots($siteUrl . '/robots.txt'),
            $this->checkSitemap($siteUrl . '/sitemap-site.xml', 'sitemap-site.xml'),
        ];

        return array_merge($rows, $this->checkSitemapIndex($siteUrl . '/sitemap-index.xml'));
    }

    private function checkRobots(string $url): array
    {
        try {
            $file = $this->seoFilesClient->fetch($url);
        } catch (\Throwable $e) {
            return $this->errorRow($url, 'robots.txt', 'label.health_check_seo_files_call_failed', $e->getMessage());
        }

        if ($file['statusCode'] >= 400 || '' === trim($file['content'])) {
            return $this->row($url, 'robots.txt', HealthCheckResult::STATUS_ERROR, 'label.health_check_robots_missing');
        }

        if ($this->blocksEverything($file['content'])) {
            return $this->row($url, 'robots.txt', HealthCheckResult::STATUS_WARNING, 'label.health_check_robots_blocks_everything');
        }

        return $this->row($url, 'robots.txt', HealthCheckResult::STATUS_OK, 'label.health_check_robots_ok');
    }

    private function checkSitemap(string $url, string $label): array
    {
        try {
            $file = $this->seoFilesClient->fetch($url);
        } catch (\Throwable $e) {
            return $this->errorRow($url, $label, 'label.health_check_seo_files_call_failed', $e->getMessage());
        }

        if ($file['statusCode'] >= 400) {
            return $this->row($url, $label, HealthCheckResult::STATUS_ERROR, 'label.health_check_sitemap_missing');
        }

        if (!str_contains($file['content'], '<urlset') && !str_contains($file['content'], '<sitemapindex')) {
            return $this->row($url, $label, HealthCheckResult::STATUS_ERROR, 'label.health_check_sitemap_invalid');
        }

        return $this->row($url, $label, HealthCheckResult::STATUS_OK, 'label.health_check_sitemap_ok');
    }

    // sitemap-index.xml is optional (only multi-sitemap setups via the app-level scaffolded command generate one) - a missing/unreachable index yields no rows at all, unlike a missing sitemap-site.xml. When present, each referenced sitemap gets its own row (via checkSitemap()) alongside the index's own row, so a broken child is pinpointed instead of buried in a single aggregate result
    private function checkSitemapIndex(string $url): array
    {
        try {
            $file = $this->seoFilesClient->fetch($url);
        } catch (\Throwable $e) {
            return [];
        }

        if ($file['statusCode'] >= 400) {
            return [];
        }

        if (!str_contains($file['content'], '<sitemapindex')) {
            return [$this->row($url, 'sitemap-index.xml', HealthCheckResult::STATUS_ERROR, 'label.health_check_sitemap_index_invalid')];
        }

        $locations = $this->extractSitemapLocations($file['content']);
        if ([] === $locations) {
            return [$this->row($url, 'sitemap-index.xml', HealthCheckResult::STATUS_WARNING, 'label.health_check_sitemap_index_empty')];
        }

        $rows = [$this->row($url, 'sitemap-index.xml', HealthCheckResult::STATUS_OK, 'label.health_check_sitemap_index_ok', ['%count%' => count($locations)])];
        foreach ($locations as $location) {
            $rows[] = $this->checkSitemap($location, $this->labelFromUrl($location));
        }

        return $rows;
    }

    private function labelFromUrl(string $url): string
    {
        return basename(parse_url($url, PHP_URL_PATH) ?? $url);
    }

    // @return list<string> the <loc> of every <sitemap> entry - libxml errors are swallowed since malformed XML here just yields no locations, already reported separately by the "<sitemapindex" substring check above
    private function extractSitemapLocations(string $content): array
    {
        $previousSetting = libxml_use_internal_errors(true);
        $xml = simplexml_load_string($content);
        libxml_use_internal_errors($previousSetting);

        if (false === $xml) {
            return [];
        }

        $locations = [];
        foreach ($xml->sitemap as $sitemap) {
            $location = trim((string) $sitemap->loc);
            if ('' !== $location) {
                $locations[] = $location;
            }
        }

        return $locations;
    }

    // True only if a "Disallow: /" line sits within a "User-agent: *" group - a plain substring search would false-positive on eg. "Disallow: /admin/" or a Disallow scoped to a specific, non-wildcard bot
    private function blocksEverything(string $content): bool
    {
        $appliesToAll = false;
        foreach (preg_split('/\r\n|\r|\n/', $content) as $line) {
            $line = trim(preg_replace('/#.*/', '', $line));
            if ('' === $line) {
                continue;
            }
            if (preg_match('/^User-agent:\s*(.+)$/i', $line, $matches)) {
                $appliesToAll = '*' === trim($matches[1]);
                continue;
            }
            if ($appliesToAll && preg_match('/^Disallow:\s*\/\s*$/i', $line)) {
                return true;
            }
        }

        return false;
    }

    private function row(string $url, string $label, string $status, string $translationId, array $params = []): array
    {
        return [
            'url' => $url,
            'label' => $label,
            'status' => $status,
            'summary' => $this->translator->trans($translationId, $params + ['%file%' => $label], 'site'),
            'details' => [],
        ];
    }

    private function errorRow(string $url, string $label, string $translationId, string $message): array
    {
        return [
            'url' => $url,
            'label' => $label,
            'status' => HealthCheckResult::STATUS_ERROR,
            'summary' => $this->translator->trans($translationId, ['%message%' => $message], 'site'),
            'details' => ['error' => $message],
        ];
    }
}
