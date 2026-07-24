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
use c975L\SiteBundle\Service\ContentQualityClient;
use c975L\SiteBundle\Service\PageEditUrlResolver;
use c975L\SiteBundle\Service\PageExistenceChecker;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

// Local content-quality checks (missing meta description, missing H1, images without alt text, broken internal links) against every published page's own rendered HTML - unrelated to Lighthouse/W3C, no external API. Broken links are checked once per unique url across the whole run (not once per page that links to it), see checkBrokenLinks()
class ContentQualityHealthCheckProvider implements HealthCheckProviderInterface
{
    use HealthCheckErrorRowTrait;

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly ContentQualityClient $contentQualityClient,
        private readonly PagePublicUrlResolver $pagePublicUrlResolver,
        private readonly PageEditUrlResolver $pageEditUrlResolver,
        private readonly PageExistenceChecker $pageExistenceChecker,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getKind(): string
    {
        return 'content-quality';
    }

    public function runChecks(): array
    {
        $pages = [];
        foreach ($this->pageRepository->findAllOrdered() as $page) {
            $url = $this->pagePublicUrlResolver->resolve($page);
            if (null === $url) {
                return [];
            }

            $pages[] = ['url' => $url, 'label' => $page->getTitle(), 'editUrl' => $this->pageEditUrlResolver->resolve($page)];
        }

        $analyses = $this->analyzePages($pages);
        $brokenLinks = $this->checkBrokenLinks($analyses);

        return array_map(fn (array $entry) => $this->buildRow($entry, $brokenLinks), $analyses);
    }

    // Every analysis request is fired before any response is read, letting the HttpClient transport run them concurrently instead of paying each page's timeout serially. Failures are kept alongside successes rather than thrown away, so buildRow() can still emit a row for a page whose content couldn't be analyzed. Each page's existence is checked first (blocking, same as W3cHealthCheckProvider) - a page present in a lower environment's database but never deployed to the checked url otherwise surfaces as a confusing raw HTTP 404 from the analysis request itself. Rows are keyed by the page's own position (not appended as each branch resolves) and ksort()ed back at the end, so a not-found/failed page in the middle of the list doesn't shuffle every row after it to the bottom
    private function analyzePages(array $pages): array
    {
        $analyses = [];
        $pending = [];
        foreach ($pages as $index => ['url' => $url, 'label' => $label, 'editUrl' => $editUrl]) {
            if (!$this->pageExistenceChecker->exists($url)) {
                $analyses[$index] = ['url' => $url, 'label' => $label, 'editUrl' => $editUrl, 'analysis' => null, 'error' => null, 'notFound' => true];
                continue;
            }

            try {
                $pending[$index] = ['url' => $url, 'label' => $label, 'editUrl' => $editUrl, 'response' => $this->contentQualityClient->request($url)];
            } catch (\Throwable $e) {
                $analyses[$index] = ['url' => $url, 'label' => $label, 'editUrl' => $editUrl, 'analysis' => null, 'error' => $e->getMessage(), 'notFound' => false];
            }
        }

        foreach ($pending as $index => ['url' => $url, 'label' => $label, 'editUrl' => $editUrl, 'response' => $response]) {
            try {
                $analyses[$index] = ['url' => $url, 'label' => $label, 'editUrl' => $editUrl, 'analysis' => $this->contentQualityClient->read($response, $url), 'error' => null, 'notFound' => false];
            } catch (\Throwable $e) {
                $analyses[$index] = ['url' => $url, 'label' => $label, 'editUrl' => $editUrl, 'analysis' => null, 'error' => $e->getMessage(), 'notFound' => false];
            }
        }

        ksort($analyses);

        return array_values($analyses);
    }

    // Every internal link found on every page, deduped, each checked once regardless of how many pages link to it - every HEAD request fired before any is read, same concurrency as analyzePages()
    private function checkBrokenLinks(array $analyses): array
    {
        $allLinks = [];
        foreach ($analyses as $entry) {
            foreach ($entry['analysis']['internalLinks'] ?? [] as $link) {
                $allLinks[$link] = true;
            }
        }

        $pending = [];
        foreach (array_keys($allLinks) as $link) {
            $pending[$link] = $this->contentQualityClient->requestLinkCheck($link);
        }

        $broken = [];
        foreach ($pending as $link => $response) {
            $broken[$link] = $this->contentQualityClient->readLinkCheck($response);
        }

        return $broken;
    }

    private function buildRow(array $entry, array $brokenLinks): array
    {
        if ($entry['notFound']) {
            return [
                'url' => $entry['url'],
                'label' => $entry['label'],
                'status' => HealthCheckResult::STATUS_SKIPPED,
                'summary' => $this->translator->trans('label.health_check_page_not_found', [], 'site'),
                'details' => [],
                'editUrl' => $entry['editUrl'],
            ];
        }

        if (null !== $entry['error']) {
            return $this->errorRow($entry['url'], $entry['label'], 'label.health_check_content_quality_call_failed', $entry['error'], $entry['editUrl']);
        }

        $analysis = $entry['analysis'];
        $brokenOnThisPage = array_values(array_filter($analysis['internalLinks'], static fn (string $link) => $brokenLinks[$link] ?? false));

        $issues = [];
        if (!$analysis['hasDescription']) {
            $issues[] = $this->translator->trans('label.health_check_content_quality_no_description', [], 'site');
        }
        if (!$analysis['hasH1']) {
            $issues[] = $this->translator->trans('label.health_check_content_quality_no_h1', [], 'site');
        }
        if ($analysis['imagesWithoutAlt'] > 0) {
            $issues[] = $this->translator->trans('label.health_check_content_quality_images_without_alt', ['%count%' => $analysis['imagesWithoutAlt']], 'site');
        }
        if ($brokenOnThisPage) {
            $issues[] = $this->translator->trans('label.health_check_content_quality_broken_links', ['%count%' => \count($brokenOnThisPage)], 'site');
        }

        return [
            'url' => $entry['url'],
            'label' => $entry['label'],
            'status' => match (true) {
                [] === $issues => HealthCheckResult::STATUS_OK,
                [] !== $brokenOnThisPage => HealthCheckResult::STATUS_ERROR,
                default => HealthCheckResult::STATUS_WARNING,
            },
            'summary' => $issues ? implode(' · ', $issues) : $this->translator->trans('label.health_check_content_quality_ok', [], 'site'),
            // hasDescription/hasH1 kept alongside imagesWithoutAlt/brokenLinks (already needed for the summary/status above) so PageHealthCheckAdviceBuilder can tell issues apart without re-parsing the translated summary text
            'details' => [
                'hasDescription' => $analysis['hasDescription'],
                'hasH1' => $analysis['hasH1'],
                'imagesWithoutAlt' => $analysis['imagesWithoutAlt'],
                'brokenLinks' => $brokenOnThisPage,
            ],
            'editUrl' => $entry['editUrl'],
        ];
    }
}
