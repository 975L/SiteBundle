<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\ContentQualityAnalyzer;
use c975L\SiteBundle\Management\ContentQualityHealthCheckProvider;
use c975L\SiteBundle\Management\PageBlockLocator;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\ContentQualityClient;
use c975L\SiteBundle\Service\PageEditUrlResolver;
use c975L\SiteBundle\Service\PageExistenceChecker;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Tests\PagePublicUrlGeneratorTestTrait;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\HttpClient\ResponseInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class ContentQualityHealthCheckProviderTest extends TestCase
{
    use PagePublicUrlGeneratorTestTrait;

    // 42 characters, inside the recommended 30-65 window
    private const GOOD_TITLE = 'Une page de test au titre bien dimensionné';
    // 84 characters, inside the recommended 50-160 window
    private const GOOD_DESCRIPTION = 'Une description de test suffisamment longue pour passer le seuil minimal recommandé.';
    private const GOOD_SOCIAL_TAGS = ['og:title' => 'T', 'og:description' => 'D', 'og:image' => '/media/og.png'];

    // Every analysis a test doesn't say otherwise about is a clean one - each test overrides only the key it is actually about (see the "+ self::GOOD_ANALYSIS" unions below), so adding a check here doesn't turn every other test's page orange
    private const GOOD_ANALYSIS = ['title' => self::GOOD_TITLE, 'description' => self::GOOD_DESCRIPTION, 'hasDescription' => true, 'hasH1' => true, 'imagesWithoutAlt' => [], 'socialTags' => self::GOOD_SOCIAL_TAGS, 'internalLinks' => [], 'externalLinks' => [], 'linkTexts' => []];

    private function createPage(string $slug): Page
    {
        $page = new Page();
        $page->setSlug($slug);
        $page->setTitle($slug);

        return $page;
    }

    private function createPageRepository(array $pages): PageRepository
    {
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findAllOrdered')->willReturn($pages);

        return $repository;
    }

    private function createUrlResolver(?string $siteUrl = 'https://example.com'): PagePublicUrlResolver
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        return new PagePublicUrlResolver($configService, $this->createUrlGenerator());
    }

    private function createPageExistenceChecker(bool $exists = true): PageExistenceChecker
    {
        $checker = $this->createStub(PageExistenceChecker::class);
        $checker->method('exists')->willReturn($exists);

        return $checker;
    }

    // request()'s return value is opaque to the provider - only read() (mocked separately per test) gives it meaning
    private function stubResponse(): ResponseInterface
    {
        return $this->createStub(ResponseInterface::class);
    }

    // Configures a ContentQualityClient stub/mock's request()/read() pair to behave like a synchronous analyze(), for tests that don't otherwise need to distinguish the two calls
    private function stubAnalyze(ContentQualityClient $client, array $analysis): void
    {
        $client->method('request')->willReturn($this->stubResponse());
        $client->method('read')->willReturn($analysis);
    }

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $id, array $parameters = []) => strtr($id, $parameters)
        );

        return $translator;
    }

    private function createPageEditUrlResolver(string $url = '/management/page/1/edit'): PageEditUrlResolver
    {
        $resolver = $this->createStub(PageEditUrlResolver::class);
        $resolver->method('resolve')->willReturn($url);

        return $resolver;
    }

    // Nothing traced back to a block by default (locateImage()/locateLink() return null) - the block matching itself is PageBlockLocatorTest's job, here it only has to not get in the way
    private function createPageBlockLocator(?array $located = null): PageBlockLocator
    {
        $locator = $this->createStub(PageBlockLocator::class);
        $locator->method('locateImage')->willReturn($located);
        $locator->method('locateLink')->willReturn($located);

        return $locator;
    }

    private function createProvider(
        array $pages,
        ContentQualityClient $client,
        ?string $siteUrl = 'https://example.com',
        ?PageExistenceChecker $pageExistenceChecker = null,
        ?PageBlockLocator $pageBlockLocator = null,
    ): ContentQualityHealthCheckProvider {
        // The real ContentQualityAnalyzer rather than a stub: it holds every check this provider is about, so testing through it is testing what actually runs
        return new ContentQualityHealthCheckProvider(
            $this->createPageRepository($pages),
            $this->createUrlResolver($siteUrl),
            $this->createPageEditUrlResolver(),
            new ContentQualityAnalyzer(
                $client,
                $pageExistenceChecker ?? $this->createPageExistenceChecker(),
                $pageBlockLocator ?? $this->createPageBlockLocator(),
                $this->createTranslator(),
            ),
        );
    }

    public function testGetKindReturnsContentQuality(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $provider = $this->createProvider([], $client);

        $this->assertSame('content-quality', $provider->getKind());
    }

    public function testRunChecksReturnsEmptyArrayWithoutASiteUrl(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $provider = $this->createProvider([$this->createPage('home')], $client, null);

        $this->assertSame([], $provider->runChecks());
    }

    public function testRunChecksStatusIsOkWhenEverythingIsFine(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, self::GOOD_ANALYSIS);

        $provider = $this->createProvider([$this->createPage('home')], $client);

        $result = $provider->runChecks()[0];
        $this->assertSame(HealthCheckResult::STATUS_OK, $result['status']);
    }

    public function testRunChecksStatusIsWarningWhenDescriptionIsMissing(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, ['hasDescription' => false, 'hasH1' => true, 'imagesWithoutAlt' => [], 'internalLinks' => [], 'linkTexts' => []] + self::GOOD_ANALYSIS);

        $provider = $this->createProvider([$this->createPage('home')], $client);

        $result = $provider->runChecks()[0];
        $this->assertSame(HealthCheckResult::STATUS_WARNING, $result['status']);
        $this->assertStringContainsString('label.health_check_content_quality_no_description', $result['summary']);
    }

    public function testRunChecksStatusIsWarningWhenH1IsMissing(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, ['hasDescription' => true, 'hasH1' => false, 'imagesWithoutAlt' => [], 'internalLinks' => [], 'linkTexts' => []] + self::GOOD_ANALYSIS);

        $provider = $this->createProvider([$this->createPage('home')], $client);

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $provider->runChecks()[0]['status']);
    }

    public function testRunChecksStatusIsWarningWhenImagesAreMissingAlt(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, ['hasDescription' => true, 'hasH1' => true, 'imagesWithoutAlt' => ['/media/a.jpg', '/media/b.jpg', '/media/c.jpg'], 'internalLinks' => [], 'linkTexts' => []] + self::GOOD_ANALYSIS);

        $provider = $this->createProvider([$this->createPage('home')], $client);

        $result = $provider->runChecks()[0];
        $this->assertSame(HealthCheckResult::STATUS_WARNING, $result['status']);
        $this->assertCount(3, $result['details']['imagesWithoutAlt']);
        $this->assertSame('/media/a.jpg', $result['details']['imagesWithoutAlt'][0]['src']);
    }

    public function testRunChecksReturnsAWarningRowWhenThePageIsNotDeployed(): void
    {
        $client = $this->createMock(ContentQualityClient::class);
        $client->expects($this->never())->method('request');

        $provider = $this->createProvider([$this->createPage('home')], $client, pageExistenceChecker: $this->createPageExistenceChecker(false));

        $result = $provider->runChecks()[0];
        $this->assertSame(HealthCheckResult::STATUS_SKIPPED, $result['status']);
        $this->assertSame('label.health_check_page_not_found', $result['summary']);
    }

    public function testRunChecksReturnsAnErrorRowWhenTheCallFails(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $client->method('request')->willReturn($this->stubResponse());
        $client->method('read')->willThrowException(new \RuntimeException('Timeout'));

        $provider = $this->createProvider([$this->createPage('home')], $client);

        $result = $provider->runChecks()[0];
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $result['status']);
        $this->assertSame(['error' => 'Timeout'], $result['details']);
    }

    public function testRunChecksStatusIsErrorWhenAPageHasABrokenInternalLink(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, [
            'hasDescription' => true,
            'hasH1' => true,
            'imagesWithoutAlt' => [],
            'internalLinks' => ['https://example.com/pages/missing/'],
            'linkTexts' => ['https://example.com/pages/missing/' => 'Nos tarifs'],
        ] + self::GOOD_ANALYSIS);
        $client->method('requestLinkCheck')->willReturn($this->stubResponse());
        $client->method('readLinkCheck')->willReturn(ContentQualityClient::LINK_BROKEN);

        $provider = $this->createProvider([$this->createPage('home')], $client);

        $result = $provider->runChecks()[0];
        $this->assertSame(HealthCheckResult::STATUS_ERROR, $result['status']);
        $this->assertSame('https://example.com/pages/missing/', $result['details']['brokenLinks'][0]['url']);
        $this->assertSame('Nos tarifs', $result['details']['brokenLinks'][0]['text']);
    }

    public function testRunChecksOnlyChecksEachUniqueLinkOnceAcrossAllPages(): void
    {
        $client = $this->createMock(ContentQualityClient::class);
        $this->stubAnalyze($client, [
            'hasDescription' => true,
            'hasH1' => true,
            'imagesWithoutAlt' => [],
            'internalLinks' => ['https://example.com/pages/shared/'],
            'linkTexts' => [],
        ] + self::GOOD_ANALYSIS);
        $client->expects($this->once())->method('requestLinkCheck')->with('https://example.com/pages/shared/')->willReturn($this->stubResponse());
        $client->method('readLinkCheck')->willReturn(ContentQualityClient::LINK_OK);

        $provider = $this->createProvider([$this->createPage('home'), $this->createPage('contact')], $client);

        $results = $provider->runChecks();
        $this->assertCount(2, $results);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[0]['status']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[1]['status']);
    }

    // A not-found page in the middle of the list must not shuffle the rows after it to the bottom - requests are fired concurrently (not-found pages skip firing one), but rows must still come back keyed to their own page's position
    public function testRunChecksKeepsRowsInPageOrderWhenAMiddlePageIsNotFound(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, self::GOOD_ANALYSIS);

        $pageExistenceChecker = $this->createStub(PageExistenceChecker::class);
        $pageExistenceChecker->method('exists')->willReturnCallback(
            static fn (string $url): bool => !str_contains($url, 'contact')
        );

        $provider = $this->createProvider(
            [$this->createPage('home'), $this->createPage('contact'), $this->createPage('about')],
            $client,
            pageExistenceChecker: $pageExistenceChecker,
        );

        $results = $provider->runChecks();

        $this->assertCount(3, $results);
        $this->assertSame('https://example.com/', $results[0]['url']);
        $this->assertSame(HealthCheckResult::STATUS_SKIPPED, $results[1]['status']);
        $this->assertSame('https://example.com/pages/about', $results[2]['url']);
    }

    // Each listed image/link carries the block holding it, so the Health check panel can link straight to it
    public function testRunChecksAttachesTheOwningBlockToEachImageAndLink(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, [
            'hasDescription' => true,
            'hasH1' => true,
            'imagesWithoutAlt' => ['/media/beach.jpg'],
            'internalLinks' => ['https://example.com/pages/missing/'],
            'linkTexts' => [],
        ] + self::GOOD_ANALYSIS);
        $client->method('requestLinkCheck')->willReturn($this->stubResponse());
        $client->method('readLinkCheck')->willReturn(ContentQualityClient::LINK_BROKEN);

        $locator = $this->createPageBlockLocator(['label' => '(#1) Hero', 'editUrl' => '/management?focusBlock=12']);
        $provider = $this->createProvider([$this->createPage('home')], $client, pageBlockLocator: $locator);

        $details = $provider->runChecks()[0]['details'];
        $this->assertSame('(#1) Hero', $details['imagesWithoutAlt'][0]['block']);
        $this->assertSame('/management?focusBlock=12', $details['imagesWithoutAlt'][0]['editUrl']);
        $this->assertSame('(#1) Hero', $details['brokenLinks'][0]['block']);
    }

    public function testRunChecksIncludesThePageEditUrl(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, self::GOOD_ANALYSIS);

        $provider = $this->createProvider([$this->createPage('home')], $client);

        $this->assertSame('/management/page/1/edit', $provider->runChecks()[0]['editUrl']);
    }

    // A link the run could never conclude on stays out of the broken list - a timeout of the run's own making says nothing about the link
    public function testRunChecksDoesNotReportAnInconclusiveLinkAsBroken(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, ['hasDescription' => true, 'hasH1' => true, 'imagesWithoutAlt' => [], 'internalLinks' => ['https://example.com/pages/slow/'], 'linkTexts' => []] + self::GOOD_ANALYSIS);
        $client->method('requestLinkCheck')->willReturn($this->stubResponse());
        $client->method('requestLinkCheckFallback')->willReturn($this->stubResponse());
        $client->method('readLinkCheck')->willReturn(ContentQualityClient::LINK_UNKNOWN);

        $result = $this->createProvider([$this->createPage('home')], $client)->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_OK, $result['status']);
        $this->assertSame([], $result['details']['brokenLinks']);
    }

    // Whatever the HEAD pass couldn't conclude on gets one GET retry before anything is called broken
    public function testRunChecksRetriesAnInconclusiveLinkInGet(): void
    {
        $client = $this->createMock(ContentQualityClient::class);
        $this->stubAnalyze($client, ['hasDescription' => true, 'hasH1' => true, 'imagesWithoutAlt' => [], 'internalLinks' => ['https://example.com/pages/flaky/'], 'linkTexts' => []] + self::GOOD_ANALYSIS);
        $client->method('requestLinkCheck')->willReturn($this->stubResponse());
        $client->expects($this->once())->method('requestLinkCheckFallback')->with('https://example.com/pages/flaky/')->willReturn($this->stubResponse());
        $client->method('readLinkCheck')->willReturnOnConsecutiveCalls(ContentQualityClient::LINK_UNKNOWN, ContentQualityClient::LINK_BROKEN);

        $result = $this->createProvider([$this->createPage('home')], $client)->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $result['status']);
        $this->assertSame('https://example.com/pages/flaky/', $result['details']['brokenLinks'][0]['url']);
    }

    // A conclusive HEAD is never retried
    public function testRunChecksDoesNotRetryAConclusiveLink(): void
    {
        $client = $this->createMock(ContentQualityClient::class);
        $this->stubAnalyze($client, ['hasDescription' => true, 'hasH1' => true, 'imagesWithoutAlt' => [], 'internalLinks' => ['https://example.com/pages/missing/'], 'linkTexts' => []] + self::GOOD_ANALYSIS);
        $client->method('requestLinkCheck')->willReturn($this->stubResponse());
        $client->expects($this->never())->method('requestLinkCheckFallback');
        $client->method('readLinkCheck')->willReturn(ContentQualityClient::LINK_BROKEN);

        $this->assertSame(HealthCheckResult::STATUS_ERROR, $this->createProvider([$this->createPage('home')], $client)->runChecks()[0]['status']);
    }

    // Requests are fired in bounded batches, not all at once - a site-wide burst would queue past the HttpClient's own per-host cap while each queued request's timeout is already running
    public function testRunChecksFiresLinkChecksInBoundedBatches(): void
    {
        $links = array_map(static fn (int $i): string => 'https://example.com/pages/p' . $i . '/', range(1, 25));
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, ['hasDescription' => true, 'hasH1' => true, 'imagesWithoutAlt' => [], 'internalLinks' => $links, 'linkTexts' => []] + self::GOOD_ANALYSIS);

        $calls = [];
        $client->method('requestLinkCheck')->willReturnCallback(function () use (&$calls): ResponseInterface {
            $calls[] = 'request';

            return $this->stubResponse();
        });
        $client->method('readLinkCheck')->willReturnCallback(static function () use (&$calls): string {
            $calls[] = 'read';

            return ContentQualityClient::LINK_OK;
        });

        $this->createProvider([$this->createPage('home')], $client)->runChecks();

        // Longest run of consecutive requests before anything is read, ie. how many were ever in flight at once
        $longestBurst = 0;
        $burst = 0;
        foreach ($calls as $call) {
            $burst = 'request' === $call ? $burst + 1 : 0;
            $longestBurst = max($longestBurst, $burst);
        }

        $this->assertCount(50, $calls);
        $this->assertLessThanOrEqual(10, $longestBurst);
    }

    // A url the HttpClient rejects outright (request() throwing before any response exists) is as inconclusive as a failed transfer, not a whole run lost
    public function testRunChecksSurvivesALinkRequestThrowing(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, ['hasDescription' => true, 'hasH1' => true, 'imagesWithoutAlt' => [], 'internalLinks' => ['https://example.com/pages/bad/'], 'linkTexts' => []] + self::GOOD_ANALYSIS);
        $client->method('requestLinkCheck')->willThrowException(new \RuntimeException('Invalid URL'));
        $client->method('requestLinkCheckFallback')->willThrowException(new \RuntimeException('Invalid URL'));

        $result = $this->createProvider([$this->createPage('home')], $client)->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_OK, $result['status']);
        $this->assertSame([], $result['details']['brokenLinks']);
    }

    public function testRunChecksStatusIsWarningWhenTheTitleIsMissing(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, ['title' => ''] + self::GOOD_ANALYSIS);

        $result = $this->createProvider([$this->createPage('home')], $client)->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $result['status']);
        $this->assertStringContainsString('label.health_check_content_quality_no_title', $result['summary']);
        $this->assertSame('missing', $result['details']['titleIssue']);
    }

    public function testRunChecksStatusIsWarningWhenTheTitleIsTooShort(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, ['title' => 'Accueil'] + self::GOOD_ANALYSIS);

        $result = $this->createProvider([$this->createPage('home')], $client)->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $result['status']);
        $this->assertStringContainsString('label.health_check_content_quality_title_too_short', $result['summary']);
        $this->assertSame('short', $result['details']['titleIssue']);
        $this->assertSame(7, $result['details']['titleLength']);
    }

    public function testRunChecksStatusIsWarningWhenTheTitleIsTooLong(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, ['title' => str_repeat('a', ContentQualityAnalyzer::TITLE_MAX_LENGTH + 1)] + self::GOOD_ANALYSIS);

        $result = $this->createProvider([$this->createPage('home')], $client)->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $result['status']);
        $this->assertSame('long', $result['details']['titleIssue']);
    }

    // Accented characters are one character each, not the two bytes strlen() would count
    public function testRunChecksMeasuresTheTitleInCharactersNotBytes(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, self::GOOD_ANALYSIS);

        $result = $this->createProvider([$this->createPage('home')], $client)->runChecks()[0];

        $this->assertSame(42, $result['details']['titleLength']);
        $this->assertNull($result['details']['titleIssue']);
    }

    public function testRunChecksStatusIsWarningWhenTheDescriptionIsTooShort(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, ['description' => 'Trop court.'] + self::GOOD_ANALYSIS);

        $result = $this->createProvider([$this->createPage('home')], $client)->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $result['status']);
        $this->assertStringContainsString('label.health_check_content_quality_description_too_short', $result['summary']);
        $this->assertSame('short', $result['details']['descriptionIssue']);
    }

    // "Add one" and "make it longer" are two different things to do - a page with no description at all only gets the first
    public function testRunChecksDoesNotReportAMissingDescriptionAsTooShort(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, ['description' => '', 'hasDescription' => false] + self::GOOD_ANALYSIS);

        $result = $this->createProvider([$this->createPage('home')], $client)->runChecks()[0];

        $this->assertNull($result['details']['descriptionIssue']);
        $this->assertStringNotContainsString('label.health_check_content_quality_description_too_short', $result['summary']);
    }

    public function testRunChecksStatusIsWarningWhenShareTagsAreMissing(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, ['socialTags' => ['og:title' => 'T']] + self::GOOD_ANALYSIS);

        $result = $this->createProvider([$this->createPage('home')], $client)->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $result['status']);
        $this->assertStringContainsString('label.health_check_content_quality_missing_social_tags', $result['summary']);
        $this->assertSame(['og:description', 'og:image'], $result['details']['missingSocialTags']);
    }

    // A dead link on someone else's site isn't yours to fix on your own schedule, and it's the check most exposed to a false positive - warning, never error
    public function testRunChecksStatusIsOnlyWarningWhenAnExternalLinkIsBroken(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, [
            'externalLinks' => ['https://www.fnac.com/livre/123'],
            'linkTexts' => ['https://www.fnac.com/livre/123' => 'Acheter le livre'],
        ] + self::GOOD_ANALYSIS);
        $client->method('requestLinkCheck')->willReturn($this->stubResponse());
        $client->method('readLinkCheck')->willReturn(ContentQualityClient::LINK_BROKEN);

        $result = $this->createProvider([$this->createPage('home')], $client)->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_WARNING, $result['status']);
        $this->assertStringContainsString('label.health_check_content_quality_broken_external_links', $result['summary']);
        $this->assertSame('https://www.fnac.com/livre/123', $result['details']['brokenExternalLinks'][0]['url']);
        $this->assertSame('Acheter le livre', $result['details']['brokenExternalLinks'][0]['text']);
        // Never mixed in with this site's own dead links, which stay an error
        $this->assertSame([], $result['details']['brokenLinks']);
    }

    // An external host is hit once per run, not once per page linking to it - internal and external share the same dedup/batching pass
    public function testRunChecksChecksAnExternalLinkOnceAcrossAllPages(): void
    {
        $client = $this->createMock(ContentQualityClient::class);
        $this->stubAnalyze($client, ['externalLinks' => ['https://www.fnac.com/livre/123']] + self::GOOD_ANALYSIS);
        $client->expects($this->once())->method('requestLinkCheck')->with('https://www.fnac.com/livre/123')->willReturn($this->stubResponse());
        $client->method('readLinkCheck')->willReturn(ContentQualityClient::LINK_OK);

        $results = $this->createProvider([$this->createPage('home'), $this->createPage('contact')], $client)->runChecks();

        $this->assertSame(HealthCheckResult::STATUS_OK, $results[0]['status']);
        $this->assertSame(HealthCheckResult::STATUS_OK, $results[1]['status']);
    }

    // A bot filter answering the run says nothing about the link - LINK_UNKNOWN never reaches the report
    public function testRunChecksDoesNotReportAnInconclusiveExternalLinkAsBroken(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, ['externalLinks' => ['https://www.fnac.com/livre/123']] + self::GOOD_ANALYSIS);
        $client->method('requestLinkCheck')->willReturn($this->stubResponse());
        $client->method('requestLinkCheckFallback')->willReturn($this->stubResponse());
        $client->method('readLinkCheck')->willReturn(ContentQualityClient::LINK_UNKNOWN);

        $result = $this->createProvider([$this->createPage('home')], $client)->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_OK, $result['status']);
        $this->assertSame([], $result['details']['brokenExternalLinks']);
    }

    // Extra og:* tags beyond the required ones are no reason to flag a page
    public function testRunChecksDoesNotFlagShareTagsBeyondTheRequiredOnes(): void
    {
        $client = $this->createStub(ContentQualityClient::class);
        $this->stubAnalyze($client, ['socialTags' => self::GOOD_SOCIAL_TAGS + ['og:site_name' => 'Example']] + self::GOOD_ANALYSIS);

        $result = $this->createProvider([$this->createPage('home')], $client)->runChecks()[0];

        $this->assertSame(HealthCheckResult::STATUS_OK, $result['status']);
        $this->assertSame([], $result['details']['missingSocialTags']);
    }
}
