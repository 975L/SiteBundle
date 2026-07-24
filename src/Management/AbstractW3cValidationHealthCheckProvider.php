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
use c975L\SiteBundle\Management\Trait\HealthCheckErrorRowTrait;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PageEditUrlResolver;
use c975L\SiteBundle\Service\PageExistenceChecker;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\W3cValidatorClient;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Shared by W3cHtmlHealthCheckProvider/W3cCssHealthCheckProvider - HTML and CSS validation used to be a single "w3c" row/kind combining both, hard to scan at a glance (eg. "51 CSS warnings" buried in one long summary alongside HTML's own counts); each is now its own kind/row, this holds everything but which W3cValidatorClient method to call and which translations to use
abstract class AbstractW3cValidationHealthCheckProvider implements HealthCheckProviderInterface
{
    use HealthCheckErrorRowTrait;

    public function __construct(
        protected readonly PageRepository $pageRepository,
        protected readonly W3cValidatorClient $w3cValidatorClient,
        protected readonly PagePublicUrlResolver $pagePublicUrlResolver,
        protected readonly PageEditUrlResolver $pageEditUrlResolver,
        protected readonly PageExistenceChecker $pageExistenceChecker,
        protected readonly TranslatorInterface $translator,
    ) {
    }

    abstract protected function request(string $url): ResponseInterface;

    // ['errors' => string[], 'warnings' => string[]]
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
            if (null === $url) {
                return [];
            }

            $editUrl = $this->pageEditUrlResolver->resolve($page);
            if (!$this->pageExistenceChecker->exists($url)) {
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
            return $this->errorRow($url, $label, $this->callFailedTranslationId(), $e->getMessage(), $editUrl);
        }

        $errorCount = \count($result['errors']);
        $warningCount = \count($result['warnings']);

        return [
            'url' => $url,
            'label' => $label,
            'status' => $this->resolveStatus($errorCount, $warningCount),
            'summary' => $this->translator->trans($this->summaryTranslationId(), [
                '%errors%' => $errorCount,
                '%warnings%' => $warningCount,
            ], 'site'),
            'details' => $result,
            'editUrl' => $editUrl,
        ];
    }

    private function resolveStatus(int $errorCount, int $warningCount): string
    {
        return match (true) {
            $errorCount > 0 => HealthCheckResult::STATUS_ERROR,
            $warningCount > 0 => HealthCheckResult::STATUS_WARNING,
            default => HealthCheckResult::STATUS_OK,
        };
    }
}
