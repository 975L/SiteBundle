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
use c975L\ConfigBundle\Management\HealthCheckErrorRow;
use c975L\ConfigBundle\Management\HealthCheckExhaustiveInterface;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\UrlStatusChecker;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PageEditUrlResolver;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\PageSpeedInsightsClient;
use c975L\UiBundle\Service\ConfigEditUrlResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

// Runs PageSpeed Insights (Lighthouse performance/accessibility/best-practices/SEO scores, including the detailed WCAG-related audits under "accessibility", plus the errors-in-console audit) against every published page, for ConfigBundle's "Health check" dashboard page (see HealthCheckProviderInterface, run only from c975l:health-check:run)
class SitePageHealthCheckProvider implements HealthCheckExhaustiveInterface
{
    // Lighthouse's own thresholds for its 0-100 category scores (see https://developer.chrome.com/docs/lighthouse/performance/performance-scoring)
    private const int SCORE_THRESHOLD_OK = 90;
    private const int SCORE_THRESHOLD_WARNING = 50;

    // Slug ConfigBundle stores the PSI API key under (see SiteBundle's config/configs.json) - kept as an optional key, so a missing one only lowers PSI's quota rather than disabling the check entirely
    private const string API_KEY_SLUG = 'healthcheck-pagespeed-api-key';

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PageSpeedInsightsClient $pageSpeedInsightsClient,
        private readonly PagePublicUrlResolver $pagePublicUrlResolver,
        private readonly PageEditUrlResolver $pageEditUrlResolver,
        private readonly UrlStatusChecker $urlStatusChecker,
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
        // Thrown rather than returned empty: this kind is exhaustive, so an empty run tells HealthCheckRunner every stored row is stale and clears them. A missing site url says nothing about the pages already checked - the runner catches this and leaves the kind untouched
        if (!$this->configService->get('site-url')) {
            throw new \RuntimeException('Site url is not configured: no page url can be resolved.');
        }

        $results = [];
        if (!$this->configService->get(self::API_KEY_SLUG)) {
            $results[] = $this->missingApiKeyRow();
        }

        // One page at a time, deliberately. Firing every request up front let the HttpClient transport run them concurrently, but PSI answers by *loading the page itself*: N in-flight analyses means Google hitting this very site with N simultaneous requests, which inflates the TTFB it then measures and drags every score down - the check was scoring the load it was itself creating. It also crowds PSI's per-minute quota, where the daily one is the only limit an API key really lifts. This command runs from cron (see HealthCheckProviderInterface), so the extra wall-clock costs nothing anyone is waiting on
        $pageRows = [];
        foreach ($this->pageRepository->findAllOrdered() as $page) {
            $url = $this->pagePublicUrlResolver->resolve($page);
            $editUrl = $this->pageEditUrlResolver->resolve($page);
            if (!$this->urlStatusChecker->exists($url)) {
                $pageRows[] = $this->pageNotFoundRow($url, $page->getTitle(), $editUrl);
                continue;
            }

            $pageRows[] = $this->checkPage($url, $page->getTitle(), $editUrl);
        }

        return [...$results, ...$pageRows];
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

    private function checkPage(string $url, ?string $label, ?string $editUrl): array
    {
        try {
            $analysis = $this->pageSpeedInsightsClient->analyze($url);
        } catch (\Throwable $e) {
            return HealthCheckErrorRow::build($this->translator, 'site', $url, $label, 'label.health_check_pagespeed_call_failed', $e->getMessage(), $editUrl);
        }

        $scores = $analysis['scores'];
        $consoleErrors = $analysis['consoleErrors'];

        return [
            'url' => $url,
            'label' => $label,
            'status' => $this->pageStatus($scores, $consoleErrors),
            'summary' => $this->pageSummary($scores, $consoleErrors),
            'details' => ['scores' => $scores, 'consoleErrors' => $consoleErrors],
            'editUrl' => $editUrl,
        ];
    }

    // The worst verdict any of the four gauges earns: red below SCORE_THRESHOLD_WARNING, orange below SCORE_THRESHOLD_OK. A console error alone only ever warns - it doesn't say the page is broken, but it's worth a look
    private function pageStatus(array $scores, array $consoleErrors): string
    {
        $status = HealthCheckResult::STATUS_OK;
        foreach ($scores as $score) {
            if (null === $score) {
                continue;
            }
            if ($score < self::SCORE_THRESHOLD_WARNING) {
                return HealthCheckResult::STATUS_ERROR;
            }
            if ($score < self::SCORE_THRESHOLD_OK) {
                $status = HealthCheckResult::STATUS_WARNING;
            }
        }

        return $consoleErrors && HealthCheckResult::STATUS_OK === $status ? HealthCheckResult::STATUS_WARNING : $status;
    }

    // The four scores in one line, a gauge never returned by the API showing as "-" rather than as a 0 it didn't earn
    private function pageSummary(array $scores, array $consoleErrors): string
    {
        $summary = $this->translator->trans('label.health_check_summary_pagespeed', [
            '%performance%' => $scores['performance'] ?? '-',
            '%accessibility%' => $scores['accessibility'] ?? '-',
            '%bestPractices%' => $scores['best-practices'] ?? '-',
            '%seo%' => $scores['seo'] ?? '-',
        ], 'site');

        if (!$consoleErrors) {
            return $summary;
        }

        return $summary . ' · ' . $this->translator->trans('label.health_check_console_errors', ['%count%' => \count($consoleErrors)], 'site');
    }
}
