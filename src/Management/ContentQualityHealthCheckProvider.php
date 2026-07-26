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
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\Trait\HealthCheckErrorRowTrait;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\ContentQualityClient;
use c975L\SiteBundle\Service\PageEditUrlResolver;
use c975L\SiteBundle\Service\PageExistenceChecker;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use Symfony\Contracts\Translation\TranslatorInterface;

// Local content-quality checks (missing meta description, missing H1, images without alt text, broken internal links) against every published page's own rendered HTML - unrelated to Lighthouse/W3C, no external API. Broken links are checked once per unique url across the whole run (not once per page that links to it), see checkBrokenLinks(). Each offending image/link is listed individually in "details" (not just counted), with the block holding it resolved through PageBlockLocator so the Health check panel links straight to the block to fix
class ContentQualityHealthCheckProvider implements HealthCheckProviderInterface
{
    use HealthCheckErrorRowTrait;

    // How many link checks are kept in flight at once. Symfony's HttpClient caps concurrent connections per host, so firing every link of the whole site at once only queues the surplus - while each queued request's own timeout is already running, turning perfectly valid links into timeouts, and timeouts into "broken" rows
    private const LINK_BATCH_SIZE = 10;

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly ContentQualityClient $contentQualityClient,
        private readonly PagePublicUrlResolver $pagePublicUrlResolver,
        private readonly PageEditUrlResolver $pageEditUrlResolver,
        private readonly PageExistenceChecker $pageExistenceChecker,
        private readonly PageBlockLocator $pageBlockLocator,
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

            // The Page itself is carried along, not just its urls - buildRow() hands it to PageBlockLocator to trace each image/broken link found in the rendered html back to the block holding it
            $pages[] = ['url' => $url, 'label' => $page->getTitle(), 'editUrl' => $this->pageEditUrlResolver->resolve($page), 'page' => $page];
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
        foreach ($pages as $index => $entry) {
            if (!$this->pageExistenceChecker->exists($entry['url'])) {
                $analyses[$index] = $entry + ['analysis' => null, 'error' => null, 'notFound' => true];
                continue;
            }

            try {
                $pending[$index] = $entry + ['response' => $this->contentQualityClient->request($entry['url'])];
            } catch (\Throwable $e) {
                $analyses[$index] = $entry + ['analysis' => null, 'error' => $e->getMessage(), 'notFound' => false];
            }
        }

        foreach ($pending as $index => $entry) {
            try {
                $analyses[$index] = $entry + ['analysis' => $this->contentQualityClient->read($entry['response'], $entry['url']), 'error' => null, 'notFound' => false];
            } catch (\Throwable $e) {
                $analyses[$index] = $entry + ['analysis' => null, 'error' => $e->getMessage(), 'notFound' => false];
            }
        }

        ksort($analyses);

        return array_values($analyses);
    }

    // Every internal link found on every page, deduped, each checked once regardless of how many pages link to it - fired in batches (see LINK_BATCH_SIZE), then whatever the HEAD pass couldn't conclude on retried once in GET, so that only a real >= 400 answer ever ends up reported as broken
    private function checkBrokenLinks(array $analyses): array
    {
        $allLinks = [];
        foreach ($analyses as $entry) {
            foreach ($entry['analysis']['internalLinks'] ?? [] as $link) {
                $allLinks[$link] = true;
            }
        }

        $verdicts = $this->runLinkChecks(array_keys($allLinks), fn (string $link) => $this->contentQualityClient->requestLinkCheck($link));

        $inconclusive = array_keys($verdicts, ContentQualityClient::LINK_UNKNOWN, true);
        if ($inconclusive) {
            $verdicts = array_replace($verdicts, $this->runLinkChecks($inconclusive, fn (string $link) => $this->contentQualityClient->requestLinkCheckFallback($link)));
        }

        // A link still unknown after both passes stays out of the broken list - a timeout of the run's own making says nothing about the link
        return array_map(static fn (string $verdict): bool => ContentQualityClient::LINK_BROKEN === $verdict, $verdicts);
    }

    // Each batch requested in full before any of it is read, so a batch still runs concurrently - request() itself can throw before any response exists (a malformed url), which is as inconclusive as a failed transfer
    private function runLinkChecks(array $links, callable $request): array
    {
        $verdicts = [];
        foreach (array_chunk($links, self::LINK_BATCH_SIZE) as $batch) {
            $pending = [];
            foreach ($batch as $link) {
                try {
                    $pending[$link] = $request($link);
                } catch (\Throwable) {
                    $verdicts[$link] = ContentQualityClient::LINK_UNKNOWN;
                }
            }

            foreach ($pending as $link => $response) {
                $verdicts[$link] = $this->contentQualityClient->readLinkCheck($response);
            }
        }

        return $verdicts;
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
        $images = $this->describeImages($entry['page'], $analysis['imagesWithoutAlt']);
        $links = $this->describeLinks($entry['page'], $brokenOnThisPage, $analysis['linkTexts'] ?? []);

        $issues = [];
        if (!$analysis['hasDescription']) {
            $issues[] = $this->translator->trans('label.health_check_content_quality_no_description', [], 'site');
        }
        if (!$analysis['hasH1']) {
            $issues[] = $this->translator->trans('label.health_check_content_quality_no_h1', [], 'site');
        }
        if ($images) {
            $issues[] = $this->translator->trans('label.health_check_content_quality_images_without_alt', ['%count%' => \count($images)], 'site');
        }
        if ($links) {
            $issues[] = $this->translator->trans('label.health_check_content_quality_broken_links', ['%count%' => \count($links)], 'site');
        }

        return [
            'url' => $entry['url'],
            'label' => $entry['label'],
            'status' => match (true) {
                [] === $issues => HealthCheckResult::STATUS_OK,
                [] !== $links => HealthCheckResult::STATUS_ERROR,
                default => HealthCheckResult::STATUS_WARNING,
            },
            'summary' => $issues ? implode(' · ', $issues) : $this->translator->trans('label.health_check_content_quality_ok', [], 'site'),
            // hasDescription/hasH1 kept alongside imagesWithoutAlt/brokenLinks (already needed for the summary/status above) so PageHealthCheckAdviceBuilder can tell issues apart without re-parsing the translated summary text
            'details' => [
                'hasDescription' => $analysis['hasDescription'],
                'hasH1' => $analysis['hasH1'],
                'imagesWithoutAlt' => $images,
                'brokenLinks' => $links,
            ],
            'editUrl' => $entry['editUrl'],
        ];
    }

    // Each image listed with the block holding it, so the Health check panel can show them one by one with a direct link to fix each - a block-less image (a theme/template one, a logo) keeps its row, just without a link
    private function describeImages(Page $page, array $sources): array
    {
        return array_map(function (string $src) use ($page): array {
            $block = $this->pageBlockLocator->locateImage($page, $src);

            return ['src' => $src, 'block' => $block['label'] ?? null, 'editUrl' => $block['editUrl'] ?? null];
        }, $sources);
    }

    // Same as describeImages(), plus the link's own anchor text - "Nos tarifs" says more about which link to fix than its url does
    private function describeLinks(Page $page, array $links, array $linkTexts): array
    {
        return array_map(function (string $link) use ($page, $linkTexts): array {
            $block = $this->pageBlockLocator->locateLink($page, $link);

            return ['url' => $link, 'text' => $linkTexts[$link] ?? null, 'block' => $block['label'] ?? null, 'editUrl' => $block['editUrl'] ?? null];
        }, $links);
    }
}
