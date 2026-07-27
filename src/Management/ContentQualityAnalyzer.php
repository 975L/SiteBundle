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
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\Trait\HealthCheckErrorRowTrait;
use c975L\SiteBundle\Service\ContentQualityClient;
use c975L\SiteBundle\Service\PageExistenceChecker;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Turns a list of urls into HealthCheckResult rows - the whole content-quality check (title, meta description, H1, image alt text, Open Graph share tags, broken links), independent of where the urls came from. Shared by ContentQualityHealthCheckProvider (SiteBundle's own Page entities, with the block holding each offence traced back through PageBlockLocator) and DeclaredUrlsHealthCheckProvider (any other bundle's declared urls, which have no Page and no block behind them). No external API, only the site's own rendered HTML
class ContentQualityAnalyzer
{
    use HealthCheckErrorRowTrait;

    // How many requests are kept in flight at once, for the page analyses as well as the link checks. Symfony's HttpClient caps concurrent connections per host, so firing every url of the whole site at once only queues the surplus - while each queued request's own timeout is already running, turning perfectly valid pages/links into timeouts, and timeouts into "broken" rows. It also bounds how many responses are held in memory at once, which matters for a bundle declaring thousands of urls (see DeclaredUrlsHealthCheckProvider)
    private const BATCH_SIZE = 10;

    // Recommended <title> window, in characters. Both ends are how search results render it, not a ranking rule: under 30 wastes the strongest on-page signal a page has, over 65 gets truncated mid-word in the results page. Public so PageHealthCheckAdviceBuilder states the same numbers in its advice line rather than keeping its own copy
    public const TITLE_MIN_LENGTH = 30;
    public const TITLE_MAX_LENGTH = 65;

    // Same for the meta description - under 50 characters says too little to earn a click, over 160 is cut off
    public const DESCRIPTION_MIN_LENGTH = 50;
    public const DESCRIPTION_MAX_LENGTH = 160;

    // What a share preview actually needs to render on Facebook/LinkedIn/WhatsApp. og:url and og:type belong to the Open Graph protocol too, but nothing visible breaks without them, so they stay out rather than turning every page orange over a tag no one sees
    public const REQUIRED_SOCIAL_TAGS = ['og:title', 'og:description', 'og:image'];

    public function __construct(
        private readonly ContentQualityClient $contentQualityClient,
        private readonly PageExistenceChecker $pageExistenceChecker,
        private readonly PageBlockLocator $pageBlockLocator,
        private readonly TranslatorInterface $translator,
    ) {
    }

    // @param list<array{url: string, label: ?string, editUrl: ?string, page: ?Page}> $entries - 'page' is what lets an offence be traced back to the block holding it, so an entry without one (another bundle's declared url) still gets every check, just without the block links
    public function analyze(array $entries): array
    {
        $analyses = $this->analyzeUrls($entries);
        $brokenLinks = $this->checkBrokenLinks($analyses);

        return array_map(fn (array $entry) => $this->buildRow($entry, $brokenLinks), $analyses);
    }

    // Every analysis request of a batch is fired before any of its responses is read, letting the HttpClient transport run them concurrently instead of paying each page's timeout serially - but one batch at a time (see BATCH_SIZE), so a bundle declaring thousands of urls doesn't queue them all behind an already-running timeout, nor hold every response in memory at once. Failures are kept alongside successes rather than thrown away, so buildRow() can still emit a row for a page whose content couldn't be analyzed. Rows are keyed by the page's own position (not appended as each branch resolves) and ksort()ed back at the end, so a not-found/failed page in the middle of the list doesn't shuffle every row after it to the bottom
    private function analyzeUrls(array $entries): array
    {
        $analyses = [];
        foreach (array_chunk($entries, self::BATCH_SIZE, true) as $batch) {
            $pending = [];
            foreach ($batch as $index => $entry) {
                // A page present in a lower environment's database but never deployed to the checked url is worth telling apart from a failed analysis - the existence HEAD does it before the slower request, same as W3cHealthCheckProvider. Only for a Page though: a declared url (no Page behind it) gets the same verdict for free from its own analysis response, and doubling the request count of a bundle declaring thousands of urls is not free
                if (null !== $entry['page'] && !$this->pageExistenceChecker->exists($entry['url'])) {
                    $analyses[$index] = $entry + ['analysis' => null, 'error' => null, 'notFound' => true, 'gone' => false];
                    continue;
                }

                try {
                    $pending[$index] = $entry + ['response' => $this->contentQualityClient->request($entry['url'])];
                } catch (\Throwable $e) {
                    $analyses[$index] = $entry + ['analysis' => null, 'error' => $e->getMessage(), 'notFound' => false, 'gone' => false];
                }
            }

            foreach ($pending as $index => $entry) {
                try {
                    $analyses[$index] = $entry + ['analysis' => $this->contentQualityClient->read($entry['response'], $entry['url']), 'error' => null, 'notFound' => false, 'gone' => false];
                } catch (\Throwable $e) {
                    $analyses[$index] = $entry + ['analysis' => null, 'error' => $e->getMessage()] + $this->failureVerdict($entry['response'], null !== $entry['page']);
                }
            }
        }

        ksort($analyses);

        return array_values($analyses);
    }

    // What a failed analysis says about the url itself, rather than about the check. 404 means "not deployed here", which is only worth telling apart for a Page (it exists in database either way, which is what the existence HEAD above establishes) - a url another bundle declares in its own sitemap answering 404 is precisely the defect these "urls-<bundle>" checks exist to surface, so it stays an error. 410 is the site answering on purpose (a soft-deleted Page - see PageController's GoneHttpException - or a resource its bundle removed): nothing is broken, only the declaration is stale. Anything else (403, 5xx, a response that never completed) says nothing about the url existing and is reported as the failure it is
    // @return array{notFound: bool, gone: bool}
    private function failureVerdict(ResponseInterface $response, bool $hasPage): array
    {
        $status = $this->statusCode($response);

        return ['notFound' => 404 === $status && $hasPage, 'gone' => 410 === $status];
    }

    // A response that never completed (timeout, DNS, refused connection) throws here too, and carries no status at all
    private function statusCode(ResponseInterface $response): ?int
    {
        try {
            return $response->getStatusCode();
        } catch (\Throwable) {
            return null;
        }
    }

    // Every link found on every page, internal and external together, deduped, each checked once regardless of how many pages link to it - fired in batches (see BATCH_SIZE), then whatever the HEAD pass couldn't conclude on retried once in GET, so that only a real >= 400 answer ever ends up reported as broken. Internal and external are checked in the same pass (one dedup, one set of batches, no external host hit twice because two pages link to it) and only told apart when building each page's row
    private function checkBrokenLinks(array $analyses): array
    {
        $allLinks = [];
        foreach ($analyses as $entry) {
            foreach (array_merge($entry['analysis']['internalLinks'] ?? [], $entry['analysis']['externalLinks'] ?? []) as $link) {
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
        foreach (array_chunk($links, self::BATCH_SIZE) as $batch) {
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

        // A warning rather than an error: the site answered correctly, the resource really was removed on purpose - what's left to fix is that something still declares it
        if ($entry['gone']) {
            return [
                'url' => $entry['url'],
                'label' => $entry['label'],
                'status' => HealthCheckResult::STATUS_WARNING,
                'summary' => $this->translator->trans('label.health_check_url_gone', [], 'site'),
                'details' => [],
                'editUrl' => $entry['editUrl'],
            ];
        }

        if (null !== $entry['error']) {
            return $this->errorRow($entry['url'], $entry['label'], 'label.health_check_content_quality_call_failed', $entry['error'], $entry['editUrl']);
        }

        $details = $this->buildDetails($entry, $brokenLinks);
        $issues = $this->summarizeIssues($details);

        return [
            'url' => $entry['url'],
            'label' => $entry['label'],
            // Only a dead link on this site's own pages is an error. A dead external one is a warning: it isn't yours to fix on your own schedule, and it's the check most exposed to a false positive (a host down for an hour, a filter this run didn't get past)
            'status' => match (true) {
                [] === $issues => HealthCheckResult::STATUS_OK,
                [] !== $details['brokenLinks'] => HealthCheckResult::STATUS_ERROR,
                default => HealthCheckResult::STATUS_WARNING,
            },
            'summary' => $issues ? implode(' · ', $issues) : $this->translator->trans('label.health_check_content_quality_ok', [], 'site'),
            // hasDescription/hasH1 kept alongside imagesWithoutAlt/brokenLinks (already needed for the summary/status above) so PageHealthCheckAdviceBuilder can tell issues apart without re-parsing the translated summary text. The title/description verdicts are persisted already resolved ('short'/'long'/...) rather than as raw lengths alone, so the advice builder doesn't have to re-apply the thresholds - it only needs the length to state it back to the user
            'details' => $details,
            'editUrl' => $entry['editUrl'],
        ];
    }

    // Everything found on one page, in the shape both summarizeIssues() and PageHealthCheckAdviceBuilder read
    private function buildDetails(array $entry, array $brokenLinks): array
    {
        $analysis = $entry['analysis'];
        $titleLength = mb_strlen($analysis['title'] ?? '');
        // A description that isn't there at all is reported as missing, not as too short - "add one" and "make it longer" are two different things to do
        $descriptionLength = mb_strlen($analysis['description'] ?? '');

        return [
            'titleIssue' => $this->lengthIssue($titleLength, self::TITLE_MIN_LENGTH, self::TITLE_MAX_LENGTH),
            'titleLength' => $titleLength,
            'hasDescription' => $analysis['hasDescription'],
            'descriptionIssue' => $analysis['hasDescription'] ? $this->lengthIssue($descriptionLength, self::DESCRIPTION_MIN_LENGTH, self::DESCRIPTION_MAX_LENGTH) : null,
            'descriptionLength' => $descriptionLength,
            'hasH1' => $analysis['hasH1'],
            'missingSocialTags' => array_values(array_diff(self::REQUIRED_SOCIAL_TAGS, array_keys($analysis['socialTags'] ?? []))),
            'imagesWithoutAlt' => $this->describeImages($entry['page'], $analysis['imagesWithoutAlt']),
            'brokenLinks' => $this->describeBrokenLinks($entry, $brokenLinks, 'internalLinks'),
            'brokenExternalLinks' => $this->describeBrokenLinks($entry, $brokenLinks, 'externalLinks'),
        ];
    }

    // The links of one page that the shared check found broken - internal and external are checked in the same pass (see checkBrokenLinks()) and only told apart here, by which of the analysis' two lists they came from
    private function describeBrokenLinks(array $entry, array $brokenLinks, string $key): array
    {
        $analysis = $entry['analysis'];
        $broken = array_values(array_filter($analysis[$key] ?? [], static fn (string $link) => $brokenLinks[$link] ?? false));

        return $this->describeLinks($entry['page'], $broken, $analysis['linkTexts'] ?? []);
    }

    // One short clause per offence, joined into the row's summary - built from the same "details" the advice builder reads, so the two can never disagree on what was found
    private function summarizeIssues(array $details): array
    {
        $issues = [];
        if ('missing' === $details['titleIssue']) {
            $issues[] = $this->translator->trans('label.health_check_content_quality_no_title', [], 'site');
        } elseif ('short' === $details['titleIssue']) {
            $issues[] = $this->translator->trans('label.health_check_content_quality_title_too_short', ['%length%' => $details['titleLength']], 'site');
        } elseif ('long' === $details['titleIssue']) {
            $issues[] = $this->translator->trans('label.health_check_content_quality_title_too_long', ['%length%' => $details['titleLength']], 'site');
        }
        if (!$details['hasDescription']) {
            $issues[] = $this->translator->trans('label.health_check_content_quality_no_description', [], 'site');
        } elseif ('short' === $details['descriptionIssue']) {
            $issues[] = $this->translator->trans('label.health_check_content_quality_description_too_short', ['%length%' => $details['descriptionLength']], 'site');
        } elseif ('long' === $details['descriptionIssue']) {
            $issues[] = $this->translator->trans('label.health_check_content_quality_description_too_long', ['%length%' => $details['descriptionLength']], 'site');
        }
        if (!$details['hasH1']) {
            $issues[] = $this->translator->trans('label.health_check_content_quality_no_h1', [], 'site');
        }

        foreach (['missingSocialTags' => 'missing_social_tags', 'imagesWithoutAlt' => 'images_without_alt', 'brokenLinks' => 'broken_links', 'brokenExternalLinks' => 'broken_external_links'] as $key => $translationId) {
            if ($details[$key]) {
                $issues[] = $this->translator->trans('label.health_check_content_quality_' . $translationId, ['%count%' => \count($details[$key])], 'site');
            }
        }

        return $issues;
    }

    // 'missing'/'short'/'long', or null when the length sits inside the recommended window
    private function lengthIssue(int $length, int $min, int $max): ?string
    {
        return match (true) {
            0 === $length => 'missing',
            $length < $min => 'short',
            $length > $max => 'long',
            default => null,
        };
    }

    // Each image listed with the block holding it, so the Health check panel can show them one by one with a direct link to fix each - a block-less image (a theme/template one, a logo) keeps its row, just without a link, and so does every image of an entry with no Page behind it at all
    private function describeImages(?Page $page, array $sources): array
    {
        return array_map(function (string $src) use ($page): array {
            $block = null === $page ? null : $this->pageBlockLocator->locateImage($page, $src);

            return ['src' => $src, 'block' => $block['label'] ?? null, 'editUrl' => $block['editUrl'] ?? null];
        }, $sources);
    }

    // Same as describeImages(), plus the link's own anchor text - "Nos tarifs" says more about which link to fix than its url does
    private function describeLinks(?Page $page, array $links, array $linkTexts): array
    {
        return array_map(function (string $link) use ($page, $linkTexts): array {
            $block = null === $page ? null : $this->pageBlockLocator->locateLink($page, $link);

            return ['url' => $link, 'text' => $linkTexts[$link] ?? null, 'block' => $block['label'] ?? null, 'editUrl' => $block['editUrl'] ?? null];
        }, $links);
    }
}
