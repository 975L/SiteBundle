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
use c975L\SiteBundle\Management\ContentQualityAnalyzer;
use c975L\SiteBundle\Management\PageHealthCheckAdviceBuilder;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

class PageHealthCheckAdviceBuilderTest extends TestCase
{
    private function createBuilder(?LoggerInterface $logger = null): PageHealthCheckAdviceBuilder
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(
            fn (string $id, array $parameters = [], ?string $domain = null) => $parameters ? $id . ' ' . implode(' ', $parameters) : $id
        );

        return new PageHealthCheckAdviceBuilder($translator, $logger);
    }

    // Same key the builder groups its advice under (HealthCheckAdviceBuilder::key(), ie. kind + url)
    private function key(string $kind, string $url = 'https://example.com/'): string
    {
        return $kind . '|' . $url;
    }

    private function createResult(string $kind, array $details, string $url = 'https://example.com/'): HealthCheckResult
    {
        return (new HealthCheckResult())
            ->setKind($kind)
            ->setUrl($url)
            ->setStatus(HealthCheckResult::STATUS_WARNING)
            ->setSummary('summary')
            ->setDetails($details);
    }

    public function testBuildReturnsEmptyArrayForNoResults(): void
    {
        $this->assertSame([], $this->createBuilder()->buildAdvice([]));
    }

    public function testPagespeedAdvisesOnEveryScoreBelowNinety(): void
    {
        $result = $this->createResult('pagespeed', ['scores' => ['performance' => 60, 'accessibility' => 95, 'best-practices' => 89, 'seo' => 100]]);

        $advice = $this->createBuilder()->buildAdvice([$result]);

        $this->assertCount(1, $advice);
        $this->assertCount(2, $advice[$this->key('pagespeed')]);
        $this->assertStringContainsString('60', $advice[$this->key('pagespeed')][0]['text']);
        $this->assertStringContainsString('89', $advice[$this->key('pagespeed')][1]['text']);
        $this->assertSame('https://pagespeed.web.dev/report?url=https%3A%2F%2Fexample.com%2F', $advice[$this->key('pagespeed')][0]['url']);
    }

    public function testPagespeedGivesNoAdviceWhenEveryScoreIsGood(): void
    {
        $result = $this->createResult('pagespeed', ['scores' => ['performance' => 90, 'accessibility' => 100, 'best-practices' => 95, 'seo' => 99]]);

        $this->assertSame([], $this->createBuilder()->buildAdvice([$result]));
    }

    public function testSecurityHeadersAdvisesOnMissingHeaders(): void
    {
        $result = $this->createResult('security-headers', ['missing' => ['content-security-policy', 'referrer-policy'], 'headers' => []]);

        $advice = $this->createBuilder()->buildAdvice([$result]);

        $this->assertCount(1, $advice[$this->key('security-headers')]);
        $this->assertStringContainsString('content-security-policy, referrer-policy', $advice[$this->key('security-headers')][0]['text']);
        $this->assertSame('https://securityheaders.com/?q=https%3A%2F%2Fexample.com%2F&followRedirects=on', $advice[$this->key('security-headers')][0]['url']);
    }

    public function testSecurityHeadersAdvisesOnCorsWildcard(): void
    {
        $result = $this->createResult('security-headers', ['missing' => [], 'headers' => ['access-control-allow-origin' => '*']]);

        $advice = $this->createBuilder()->buildAdvice([$result]);

        $this->assertSame('label.health_check_advice_security_headers_cors', $advice[$this->key('security-headers')][0]['text']);
    }

    public function testW3cHtmlAdvisesOnErrorsAndWarnings(): void
    {
        $result = $this->createResult('w3c-html', ['errors' => ['a'], 'warnings' => ['b', 'c']]);

        $advice = $this->createBuilder()->buildAdvice([$result]);

        $this->assertCount(2, $advice[$this->key('w3c-html')]);
        $this->assertStringContainsString('1', $advice[$this->key('w3c-html')][0]['text']);
        $this->assertStringContainsString('2', $advice[$this->key('w3c-html')][1]['text']);
        $this->assertSame('https://validator.w3.org/nu/?doc=https%3A%2F%2Fexample.com%2F', $advice[$this->key('w3c-html')][0]['url']);
    }

    public function testW3cHtmlGivesNoAdviceWhenClean(): void
    {
        $result = $this->createResult('w3c-html', ['errors' => [], 'warnings' => []]);

        $this->assertSame([], $this->createBuilder()->buildAdvice([$result]));
    }

    public function testW3cCssAdvisesOnErrorsAndWarnings(): void
    {
        $result = $this->createResult('w3c-css', ['errors' => ['a'], 'warnings' => ['b', 'c']]);

        $advice = $this->createBuilder()->buildAdvice([$result]);

        $this->assertCount(2, $advice[$this->key('w3c-css')]);
        $this->assertStringContainsString('1', $advice[$this->key('w3c-css')][0]['text']);
        $this->assertStringContainsString('2', $advice[$this->key('w3c-css')][1]['text']);
        $this->assertSame('https://jigsaw.w3.org/css-validator/validator?uri=https%3A%2F%2Fexample.com%2F&profile=css3svg', $advice[$this->key('w3c-css')][0]['url']);
    }

    public function testW3cCssGivesNoAdviceWhenClean(): void
    {
        $result = $this->createResult('w3c-css', ['errors' => [], 'warnings' => []]);

        $this->assertSame([], $this->createBuilder()->buildAdvice([$result]));
    }

    public function testContentQualityAdvisesOnEveryIssue(): void
    {
        $result = $this->createResult('content-quality', [
            'hasDescription' => false,
            'h1Count' => 0,
            'imagesWithoutAlt' => [['src' => '/media/a.jpg', 'block' => null, 'editUrl' => null]],
            'brokenLinks' => [['url' => 'https://example.com/pages/missing/', 'text' => null, 'block' => null, 'editUrl' => null]],
        ]);

        $advice = $this->createBuilder()->buildAdvice([$result]);

        $this->assertCount(4, $advice[$this->key('content-quality')]);
    }

    public function testContentQualityAdvisesOnSeveralH1(): void
    {
        $result = $this->createResult('content-quality', ['hasDescription' => true, 'h1Count' => 2, 'imagesWithoutAlt' => [], 'brokenLinks' => []]);

        $advice = $this->createBuilder()->buildAdvice([$result])[$this->key('content-quality')];

        $this->assertSame('label.health_check_advice_several_h1', $advice[0]['text']);
    }

    // First of the lines: everything below it was measured on the url the redirect landed on, not on the one declared
    public function testContentQualityAdvisesOnARedirectingUrl(): void
    {
        $result = $this->createResult('content-quality', [
            'hasDescription' => true,
            'h1Count' => 1,
            'imagesWithoutAlt' => [],
            'brokenLinks' => [],
            'redirect' => ['count' => 1, 'finalUrl' => 'https://example.com/pages/accueil'],
        ]);

        $advice = $this->createBuilder()->buildAdvice([$result])[$this->key('content-quality')];

        $this->assertStringStartsWith('label.health_check_advice_redirects', $advice[0]['text']);
        $this->assertStringContainsString('https://example.com/pages/accueil', $advice[0]['text']);
    }

    // Nothing to advise on the overwhelming majority of urls, which answer 200 straight away
    public function testContentQualityAdvisesNothingWithoutARedirect(): void
    {
        $result = $this->createResult('content-quality', ['hasDescription' => true, 'h1Count' => 1, 'imagesWithoutAlt' => [], 'brokenLinks' => [], 'redirect' => null]);

        $this->assertArrayNotHasKey($this->key('content-quality'), $this->createBuilder()->buildAdvice([$result]));
    }

    // Rows persisted before the check counted them hold "hasH1" alone, and must keep getting their advice until the next run replaces them
    public function testContentQualityStillReadsTheLegacyHasH1Detail(): void
    {
        $result = $this->createResult('content-quality', ['hasDescription' => true, 'hasH1' => false, 'imagesWithoutAlt' => [], 'brokenLinks' => []]);

        $advice = $this->createBuilder()->buildAdvice([$result])[$this->key('content-quality')];

        $this->assertSame('label.health_check_advice_no_h1', $advice[0]['text']);
    }

    // Each offending image/link listed one by one under its own advice line, with a link straight to the block holding it
    public function testContentQualityListsEachImageAndLinkAsAnItem(): void
    {
        $result = $this->createResult('content-quality', [
            'hasDescription' => true,
            'h1Count' => 1,
            'imagesWithoutAlt' => [
                ['src' => '/media/beach.jpg', 'block' => '(#1) Hero', 'editUrl' => '/management?focusBlock=12'],
                ['src' => '/media/team.jpg', 'block' => null, 'editUrl' => null],
            ],
            'brokenLinks' => [['url' => 'https://example.com/pages/missing/', 'text' => 'Nos tarifs', 'block' => '(#3) Cta', 'editUrl' => '/management?focusBlock=34']],
        ]);

        $advice = $this->createBuilder()->buildAdvice([$result])[$this->key('content-quality')];

        $this->assertStringContainsString('2', $advice[0]['text']);
        $this->assertSame('/media/beach.jpg', $advice[0]['items'][0]['text']);
        $this->assertSame('/management?focusBlock=12', $advice[0]['items'][0]['url']);
        $this->assertStringContainsString('(#1) Hero', $advice[0]['items'][0]['label']);
        // A block-less image (a theme/template one) is still listed, just with nothing to link to
        $this->assertNull($advice[0]['items'][1]['url']);
        $this->assertNull($advice[0]['items'][1]['label']);
        $this->assertSame('Nos tarifs - https://example.com/pages/missing/', $advice[1]['items'][0]['text']);
        $this->assertSame('/management?focusBlock=34', $advice[1]['items'][0]['url']);
    }

    public function testContentQualityGivesNoAdviceWhenEverythingIsFine(): void
    {
        $result = $this->createResult('content-quality', [
            'hasDescription' => true,
            'h1Count' => 1,
            'imagesWithoutAlt' => [],
            'brokenLinks' => [],
        ]);

        $this->assertSame([], $this->createBuilder()->buildAdvice([$result]));
    }

    // A result persisted before those two details became lists of their own still holds a plain count - no advice for it (the next run fixes that), but no crash either
    public function testContentQualityIgnoresPreListDetailsShape(): void
    {
        $result = $this->createResult('content-quality', [
            'hasDescription' => true,
            'h1Count' => 1,
            'imagesWithoutAlt' => 3,
            'brokenLinks' => ['https://example.com/pages/missing/'],
        ]);

        $this->assertSame([], $this->createBuilder()->buildAdvice([$result]));
    }

    public function testContentQualityAdvisesOnAMissingTitle(): void
    {
        $result = $this->createResult('content-quality', ['titleIssue' => 'missing', 'titleLength' => 0]);

        $advice = $this->createBuilder()->buildAdvice([$result])[$this->key('content-quality')];

        $this->assertSame('label.health_check_advice_no_title', $advice[0]['text']);
    }

    // The advice states the length found alongside the window to aim for, both title and description
    public function testContentQualityAdvisesOnTitleAndDescriptionLength(): void
    {
        $result = $this->createResult('content-quality', [
            'titleIssue' => 'short',
            'titleLength' => 12,
            'hasDescription' => true,
            'descriptionIssue' => 'long',
            'descriptionLength' => 210,
        ]);

        $advice = $this->createBuilder()->buildAdvice([$result])[$this->key('content-quality')];

        $this->assertCount(2, $advice);
        $this->assertStringContainsString('12', $advice[0]['text']);
        $this->assertStringContainsString((string) ContentQualityAnalyzer::TITLE_MAX_LENGTH, $advice[0]['text']);
        $this->assertStringContainsString('210', $advice[1]['text']);
    }

    // A page with no description at all only gets the "add one" line, never the length one on top of it
    public function testContentQualityDoesNotStackTheLengthAdviceOnAMissingDescription(): void
    {
        $result = $this->createResult('content-quality', ['hasDescription' => false, 'descriptionIssue' => null, 'descriptionLength' => 0]);

        $advice = $this->createBuilder()->buildAdvice([$result])[$this->key('content-quality')];

        $this->assertCount(1, $advice);
        $this->assertSame('label.health_check_advice_no_description', $advice[0]['text']);
    }

    public function testContentQualityAdvisesOnMissingShareTagsByName(): void
    {
        $result = $this->createResult('content-quality', ['missingSocialTags' => ['og:description', 'og:image']]);

        $advice = $this->createBuilder()->buildAdvice([$result])[$this->key('content-quality')];

        $this->assertStringContainsString('og:description, og:image', $advice[0]['text']);
    }

    // Its own line, apart from the internal one - the two aren't the same fix, nor the same severity
    public function testContentQualityAdvisesOnBrokenExternalLinksSeparately(): void
    {
        $result = $this->createResult('content-quality', [
            'brokenLinks' => [['url' => 'https://example.com/pages/missing/', 'text' => null]],
            'brokenExternalLinks' => [['url' => 'https://www.fnac.com/livre/123', 'text' => 'Acheter le livre']],
        ]);

        $advice = $this->createBuilder()->buildAdvice([$result])[$this->key('content-quality')];

        $this->assertCount(2, $advice);
        $this->assertStringContainsString('label.health_check_advice_broken_links', $advice[0]['text']);
        $this->assertStringContainsString('label.health_check_advice_broken_external_links', $advice[1]['text']);
        $this->assertSame('Acheter le livre - https://www.fnac.com/livre/123', $advice[1]['items'][0]['text']);
    }

    // Another bundle's declared urls hold exactly the same details as content-quality, so they get the same advice rather than the "unknown kind" silence
    public function testDeclaredUrlKindsGetTheContentQualityAdvice(): void
    {
        $result = $this->createResult('urls-book', ['titleIssue' => 'missing', 'titleLength' => 0], 'https://example.com/livre/mon-livre');

        $advice = $this->createBuilder()->buildAdvice([$result]);

        $this->assertSame('label.health_check_advice_no_title', $advice[$this->key('urls-book', 'https://example.com/livre/mon-livre')][0]['text']);
    }

    public function testDeploymentAdvisesOnEachIssue(): void
    {
        $missingRedirect = $this->createResult('deployment', ['issue' => 'https-redirect', 'statusCode' => 200], 'http://example.com/');
        $softNotFound = $this->createResult('deployment', ['issue' => 'not-404', 'statusCode' => 200], 'https://example.com/probe');

        $advice = $this->createBuilder()->buildAdvice([$missingRedirect, $softNotFound]);

        $this->assertSame('label.health_check_advice_https_redirect', $advice[$this->key('deployment', 'http://example.com/')][0]['text']);
        $this->assertStringContainsString('200', $advice[$this->key('deployment', 'https://example.com/probe')][0]['text']);
    }

    public function testDeploymentGivesNoAdviceWhenNothingIsWrong(): void
    {
        $result = $this->createResult('deployment', []);

        $this->assertSame([], $this->createBuilder()->buildAdvice([$result]));
    }

    public function testContentQualityGivesNoAdviceWhenDetailsAreMissingKeys(): void
    {
        // ContentQualityHealthCheckProvider's "page not found"/"call failed" rows carry [] or ['error' => ...] as details, not the usual analysis keys
        $result = $this->createResult('content-quality', []);

        $this->assertSame([], $this->createBuilder()->buildAdvice([$result]));
    }

    public function testSslCertificateAdvisesWhenExpiryIsWithinThirtyDays(): void
    {
        $result = $this->createResult('ssl-certificate', ['daysLeft' => 15, 'expiresAt' => '2026-08-08T00:00:00+00:00']);

        $advice = $this->createBuilder()->buildAdvice([$result]);

        $this->assertCount(1, $advice[$this->key('ssl-certificate')]);
        $this->assertSame('label.health_check_advice_ssl_certificate', $advice[$this->key('ssl-certificate')][0]['text']);
        $this->assertNull($advice[$this->key('ssl-certificate')][0]['url']);
    }

    public function testSslCertificateGivesNoAdviceWhenFarFromExpiry(): void
    {
        $result = $this->createResult('ssl-certificate', ['daysLeft' => 89, 'expiresAt' => '2026-10-21T00:00:00+00:00']);

        $this->assertSame([], $this->createBuilder()->buildAdvice([$result]));
    }

    public function testSslCertificateGivesNoAdviceWhenDetailsAreMissingKeys(): void
    {
        // "not https"/"call failed" rows carry [] or ['error' => ...] as details, no daysLeft
        $result = $this->createResult('ssl-certificate', []);

        $this->assertSame([], $this->createBuilder()->buildAdvice([$result]));
    }

    public function testMixedContentAdvisesOnInsecureResources(): void
    {
        $result = $this->createResult('mixed-content', ['insecureResources' => ['http://example.com/logo.png']]);

        $advice = $this->createBuilder()->buildAdvice([$result]);

        $this->assertCount(1, $advice[$this->key('mixed-content')]);
        $this->assertStringContainsString('1', $advice[$this->key('mixed-content')][0]['text']);
    }

    public function testMixedContentGivesNoAdviceWhenClean(): void
    {
        $result = $this->createResult('mixed-content', ['insecureResources' => []]);

        $this->assertSame([], $this->createBuilder()->buildAdvice([$result]));
    }

    public function testUnknownKindGivesNoAdvice(): void
    {
        $result = $this->createResult('some-future-kind', ['errors' => 3]);

        $this->assertSame([], $this->createBuilder()->buildAdvice([$result]));
    }

    // No advice text to show either way, but a newly-registered kind (another bundle's HealthCheckProviderInterface, or one of this bundle's own not yet wired into build()) must not go completely unnoticed
    public function testUnknownKindLogsAWarning(): void
    {
        $result = $this->createResult('some-future-kind', []);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->once())->method('warning')->with(
            $this->stringContains('some-future-kind'),
            $this->arrayHasKey('kind'),
        );

        $this->createBuilder($logger)->buildAdvice([$result]);
    }

    public function testKnownKindsNeverLogAWarning(): void
    {
        $result = $this->createResult('pagespeed', ['scores' => ['performance' => 40]]);

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $this->createBuilder($logger)->buildAdvice([$result]);
    }

    public function testBuildGroupsAdviceByKindAcrossMultipleResults(): void
    {
        $pagespeed = $this->createResult('pagespeed', ['scores' => ['performance' => 40]]);
        $w3cHtml = $this->createResult('w3c-html', ['errors' => ['a'], 'warnings' => []]);

        $advice = $this->createBuilder()->buildAdvice([$pagespeed, $w3cHtml]);

        $this->assertCount(2, $advice);
        $this->assertArrayHasKey($this->key('pagespeed'), $advice);
        $this->assertArrayHasKey($this->key('w3c-html'), $advice);
    }

    // The dashboard's Health check page lists one row per url and per kind - keying by kind alone had every page's row show the last checked page's advice
    public function testBuildKeepsTwoPagesOfTheSameKindApart(): void
    {
        $home = $this->createResult('content-quality', ['imagesWithoutAlt' => [['src' => '/media/a.jpg']]], 'https://example.com/');
        $contact = $this->createResult('content-quality', ['imagesWithoutAlt' => [['src' => '/media/b.jpg'], ['src' => '/media/c.jpg']]], 'https://example.com/pages/contact/');

        $advice = $this->createBuilder()->buildAdvice([$home, $contact]);

        $this->assertCount(1, $advice[$this->key('content-quality')][0]['items']);
        $this->assertCount(2, $advice[$this->key('content-quality', 'https://example.com/pages/contact/')][0]['items']);
    }
}
