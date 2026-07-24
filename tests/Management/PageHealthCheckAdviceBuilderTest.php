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
use c975L\SiteBundle\Management\PageHealthCheckAdviceBuilder;
use Psr\Log\LoggerInterface;
use PHPUnit\Framework\TestCase;
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

    private function createResult(string $kind, array $details): HealthCheckResult
    {
        return (new HealthCheckResult())
            ->setKind($kind)
            ->setUrl('https://example.com/')
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
        $this->assertCount(2, $advice['pagespeed']);
        $this->assertStringContainsString('60', $advice['pagespeed'][0]['text']);
        $this->assertStringContainsString('89', $advice['pagespeed'][1]['text']);
        $this->assertSame('https://pagespeed.web.dev/report?url=https%3A%2F%2Fexample.com%2F', $advice['pagespeed'][0]['url']);
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

        $this->assertCount(1, $advice['security-headers']);
        $this->assertStringContainsString('content-security-policy, referrer-policy', $advice['security-headers'][0]['text']);
        $this->assertSame('https://securityheaders.com/?q=https%3A%2F%2Fexample.com%2F&followRedirects=on', $advice['security-headers'][0]['url']);
    }

    public function testSecurityHeadersAdvisesOnCorsWildcard(): void
    {
        $result = $this->createResult('security-headers', ['missing' => [], 'headers' => ['access-control-allow-origin' => '*']]);

        $advice = $this->createBuilder()->buildAdvice([$result]);

        $this->assertSame('label.health_check_advice_security_headers_cors', $advice['security-headers'][0]['text']);
    }

    public function testW3cHtmlAdvisesOnErrorsAndWarnings(): void
    {
        $result = $this->createResult('w3c-html', ['errors' => ['a'], 'warnings' => ['b', 'c']]);

        $advice = $this->createBuilder()->buildAdvice([$result]);

        $this->assertCount(2, $advice['w3c-html']);
        $this->assertStringContainsString('1', $advice['w3c-html'][0]['text']);
        $this->assertStringContainsString('2', $advice['w3c-html'][1]['text']);
        $this->assertSame('https://validator.w3.org/nu/?doc=https%3A%2F%2Fexample.com%2F', $advice['w3c-html'][0]['url']);
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

        $this->assertCount(2, $advice['w3c-css']);
        $this->assertStringContainsString('1', $advice['w3c-css'][0]['text']);
        $this->assertStringContainsString('2', $advice['w3c-css'][1]['text']);
        $this->assertSame('https://jigsaw.w3.org/css-validator/validator?uri=https%3A%2F%2Fexample.com%2F&profile=css3svg', $advice['w3c-css'][0]['url']);
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
            'hasH1' => false,
            'imagesWithoutAlt' => 3,
            'brokenLinks' => ['https://example.com/pages/missing/'],
        ]);

        $advice = $this->createBuilder()->buildAdvice([$result]);

        $this->assertCount(4, $advice['content-quality']);
    }

    public function testContentQualityGivesNoAdviceWhenEverythingIsFine(): void
    {
        $result = $this->createResult('content-quality', [
            'hasDescription' => true,
            'hasH1' => true,
            'imagesWithoutAlt' => 0,
            'brokenLinks' => [],
        ]);

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

        $this->assertCount(1, $advice['ssl-certificate']);
        $this->assertSame('label.health_check_advice_ssl_certificate', $advice['ssl-certificate'][0]['text']);
        $this->assertNull($advice['ssl-certificate'][0]['url']);
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

        $this->assertCount(1, $advice['mixed-content']);
        $this->assertStringContainsString('1', $advice['mixed-content'][0]['text']);
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
        $this->assertArrayHasKey('pagespeed', $advice);
        $this->assertArrayHasKey('w3c-html', $advice);
    }
}
