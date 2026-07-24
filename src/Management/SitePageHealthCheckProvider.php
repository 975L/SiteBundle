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
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Management\Trait\HealthCheckErrorRowTrait;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PageEditUrlResolver;
use c975L\SiteBundle\Service\PageExistenceChecker;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\PageSpeedInsightsClient;
use c975L\UiBundle\Service\ConfigEditUrlResolver;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Runs PageSpeed Insights (Lighthouse performance/accessibility/best-practices/SEO scores, including the detailed WCAG-related audits under "accessibility", plus the errors-in-console audit) against every published page, for ConfigBundle's "Health check" dashboard page (see HealthCheckProviderInterface, run only from c975l:health-check:run)
class SitePageHealthCheckProvider implements HealthCheckProviderInterface
{
    use HealthCheckErrorRowTrait;

    // Lighthouse's own thresholds for its 0-100 category scores (see https://developer.chrome.com/docs/lighthouse/performance/performance-scoring)
    private const SCORE_THRESHOLD_OK = 90;
    private const SCORE_THRESHOLD_WARNING = 50;

    // Slug ConfigBundle stores the PSI API key under (see SiteBundle's config/configs.json) - kept as an optional key, so a missing one only lowers PSI's quota rather than disabling the check entirely
    private const API_KEY_SLUG = 'healthcheck-pagespeed-api-key';

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PageSpeedInsightsClient $pageSpeedInsightsClient,
        private readonly PagePublicUrlResolver $pagePublicUrlResolver,
        private readonly PageEditUrlResolver $pageEditUrlResolver,
        private readonly PageExistenceChecker $pageExistenceChecker,
        private readonly ConfigServiceInterface $configService,
        private readonly ConfigRepository $configRepository,
        private readonly ConfigEditUrlResolver $configEditUrlResolver,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getKind(): string
    {
        return 'pagespeed';
    }

    public function runChecks(): array
    {
        if (!$this->configService->get('site-url')) {
            return [];
        }

        $results = [];
        if (!$this->configService->get(self::API_KEY_SLUG)) {
            $results[] = $this->missingApiKeyRow();
        }

        // Every PSI request is fired before any response is read, letting the HttpClient transport run them concurrently instead of paying each page's up-to-60s timeout serially (see PageSpeedInsightsClient::request()/read()). Rows are keyed by the page's own position (not appended as each branch resolves) and ksort()ed back at the end, so a not-found page in the middle of the list doesn't shuffle every row after it to the bottom
        $pageRows = [];
        $pending = [];
        foreach ($this->pageRepository->findAllOrdered() as $index => $page) {
            $url = $this->pagePublicUrlResolver->resolve($page);
            $editUrl = $this->pageEditUrlResolver->resolve($page);
            if (!$this->pageExistenceChecker->exists($url)) {
                $pageRows[$index] = $this->pageNotFoundRow($url, $page->getTitle(), $editUrl);
                continue;
            }

            $pending[$index] = [$url, $page->getTitle(), $editUrl, $this->pageSpeedInsightsClient->request($url)];
        }

        foreach ($pending as $index => [$url, $label, $editUrl, $response]) {
            $pageRows[$index] = $this->checkPage($url, $label, $editUrl, $response);
        }

        ksort($pageRows);

        return [...$results, ...array_values($pageRows)];
    }

    // Surfaces the missing PSI key directly in the Health check table (not just the dashboard alerts, see configs.json's "severity": "warning") - its stable url (the config's own edit screen) dedupes like any page row, see HealthCheckResultRepository::findLatestPerUrlAndKind()
    private function missingApiKeyRow(): array
    {
        $config = $this->configRepository->findOneBySlug(self::API_KEY_SLUG);

        return [
            'url' => $this->configEditUrlResolver->resolve($config),
            'label' => $this->translator->trans('label.healthcheck_pagespeed_api_key', [], 'site_config'),
            'status' => HealthCheckResult::STATUS_WARNING,
            'summary' => $this->translator->trans('label.health_check_pagespeed_api_key_missing', [], 'site'),
            'details' => null,
        ];
    }

    private function pageNotFoundRow(string $url, ?string $label, ?string $editUrl): array
    {
        return [
            'url' => $url,
            'label' => $label,
            'status' => HealthCheckResult::STATUS_SKIPPED,
            'summary' => $this->translator->trans('label.health_check_page_not_found', [], 'site'),
            'details' => [],
            'editUrl' => $editUrl,
        ];
    }

    private function checkPage(string $url, ?string $label, ?string $editUrl, ResponseInterface $response): array
    {
        try {
            $analysis = $this->pageSpeedInsightsClient->read($response);
        } catch (\Throwable $e) {
            return $this->errorRow($url, $label, 'label.health_check_pagespeed_call_failed', $e->getMessage(), $editUrl);
        }

        $scores = $analysis['scores'];
        $consoleErrors = $analysis['consoleErrors'];

        $status = HealthCheckResult::STATUS_OK;
        foreach ($scores as $score) {
            if (null === $score) {
                continue;
            }
            if ($score < self::SCORE_THRESHOLD_WARNING) {
                $status = HealthCheckResult::STATUS_ERROR;
                break;
            }
            if ($score < self::SCORE_THRESHOLD_OK) {
                $status = HealthCheckResult::STATUS_WARNING;
            }
        }
        if ($consoleErrors && HealthCheckResult::STATUS_OK === $status) {
            $status = HealthCheckResult::STATUS_WARNING;
        }

        $summary = $this->translator->trans('label.health_check_summary_pagespeed', [
            '%performance%' => $scores['performance'] ?? '-',
            '%accessibility%' => $scores['accessibility'] ?? '-',
            '%bestPractices%' => $scores['best-practices'] ?? '-',
            '%seo%' => $scores['seo'] ?? '-',
        ], 'site');
        if ($consoleErrors) {
            $summary .= ' · ' . $this->translator->trans('label.health_check_console_errors', ['%count%' => \count($consoleErrors)], 'site');
        }

        return [
            'url' => $url,
            'label' => $label,
            'status' => $status,
            'summary' => $summary,
            'details' => ['scores' => $scores, 'consoleErrors' => $consoleErrors],
            'editUrl' => $editUrl,
        ];
    }
}
