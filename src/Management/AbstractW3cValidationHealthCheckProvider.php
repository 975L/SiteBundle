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
use c975L\ConfigBundle\Service\UrlStatusChecker;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PageEditUrlResolver;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\W3cValidatorClient;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Shared by W3cHtmlHealthCheckProvider/W3cCssHealthCheckProvider - HTML and CSS validation used to be a single "w3c" row/kind combining both, hard to scan at a glance (eg. "51 CSS warnings" buried in one long summary alongside HTML's own counts); each is now its own kind/row, this holds everything but which W3cValidatorClient method to call and which translations to use
abstract class AbstractW3cValidationHealthCheckProvider implements HealthCheckExhaustiveInterface
{
    public function __construct(
        protected readonly PageRepository $pageRepository,
        protected readonly W3cValidatorClient $w3cValidatorClient,
        protected readonly PagePublicUrlResolver $pagePublicUrlResolver,
        protected readonly PageEditUrlResolver $pageEditUrlResolver,
        protected readonly UrlStatusChecker $urlStatusChecker,
        protected readonly TranslatorInterface $translator,
    ) {
    }

    abstract protected function request(string $url): ResponseInterface;

    // ['errors' => string[], 'warnings' => string[], 'benignErrors'? => string[], 'benignWarnings'? => string[]] - see W3cValidatorClient::readCss() for what lands in the two optional keys, which checkPage() reads to split the actionable counts from the totals
    abstract protected function read(ResponseInterface $response): array;

    abstract protected function summaryTranslationId(): string;

    abstract protected function callFailedTranslationId(): string;

    public function runChecks(): array
    {
        // Every validator request is fired before any response is read, letting the HttpClient transport run them concurrently instead of paying each page's up-to-60s timeout serially (see W3cValidatorClient::requestHtml()/requestCss() + readHtml()/readCss()). Rows are keyed by the page's own position (not appended as each branch resolves) and ksort()ed back at the end, so a not-found page in the middle of the list doesn't shuffle every row after it to the bottom
        $results = [];
        $pending = [];
        foreach ($this->pageRepository->findAllOrdered() as $index => $page) {
            $url = $this->pagePublicUrlResolver->resolve($page);
            // Thrown rather than returned empty: this kind is exhaustive, so an empty run tells HealthCheckRunner every stored row is stale and clears them. A page whose url cannot be resolved means the site url is not configured, which says nothing about the pages already checked - the runner catches this and leaves the kind untouched
            if (null === $url) {
                throw new \RuntimeException('Site url is not configured: no page url can be resolved.');
            }

            $editUrl = $this->pageEditUrlResolver->resolve($page);
            if (!$this->urlStatusChecker->exists($url)) {
                $results[$index] = $this->pageNotFoundRow($url, $page->getTitle(), $editUrl);
                continue;
            }

            $pending[$index] = [$url, $page->getTitle(), $editUrl, $this->request($url)];
        }

        foreach ($pending as $index => [$url, $label, $editUrl, $response]) {
            $results[$index] = $this->checkPage($url, $label, $editUrl, $response);
        }

        ksort($results);

        return array_values($results);
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
            $result = $this->read($response);
        } catch (\Throwable $e) {
            return HealthCheckErrorRow::build($this->translator, 'site', $url, $label, $this->callFailedTranslationId(), $e->getMessage(), $editUrl);
        }

        // Only the actionable ones drive the status, on both counts: what the validator itself cannot judge (see W3cValidatorClient::isBenignCssError() and BENIGN_CSS_WARNING_PATTERNS) is nothing anyone can act on, and would otherwise pin this row to red or orange for good
        $errorCount = \count($result['errors']);
        $benignErrorCount = \count($result['benignErrors'] ?? []);
        $actionableCount = \count($result['warnings']);
        $benignCount = \count($result['benignWarnings'] ?? []);

        return [
            'url' => $url,
            'label' => $label,
            'status' => $this->resolveStatus($errorCount, $actionableCount),
            'summary' => $this->summary($errorCount, $benignErrorCount, $actionableCount, $benignCount),
            'details' => $result,
            'editUrl' => $editUrl,
        ];
    }

    // Both totals stay exactly the ones the W3C report itself shows (actionable + benign) - a dashboard disagreeing with the report it links to reads as broken, whichever of the two is right. Only the breakdowns are appended, and only where there is something to break down (never for HTML, which has no benign class)
    private function summary(int $errorCount, int $benignErrorCount, int $actionableCount, int $benignCount): string
    {
        $summary = $this->translator->trans($this->summaryTranslationId(), [
            '%errors%' => $errorCount + $benignErrorCount,
            '%warnings%' => $actionableCount + $benignCount,
        ], 'site');

        if ($benignErrorCount > 0) {
            $summary .= ' ' . $this->translator->trans('label.health_check_w3c_benign_errors', [
                '%actionable%' => $errorCount,
                '%benign%' => $benignErrorCount,
            ], 'site');
        }

        if (0 === $benignCount) {
            return $summary;
        }

        return $summary . ' ' . $this->translator->trans('label.health_check_w3c_benign_warnings', [
            '%actionable%' => $actionableCount,
            '%benign%' => $benignCount,
        ], 'site');
    }

    private function resolveStatus(int $errorCount, int $actionableCount): string
    {
        return match (true) {
            $errorCount > 0 => HealthCheckResult::STATUS_ERROR,
            $actionableCount > 0 => HealthCheckResult::STATUS_WARNING,
            default => HealthCheckResult::STATUS_OK,
        };
    }
}
