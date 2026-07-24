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
use c975L\ConfigBundle\Management\HealthCheckAdviceProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Turns a page's own HealthCheckResult rows into short, actionable advice lines - the Health check panel already shows *what* was found (via the shared table/gauges), this says *what to do about it*. Reads each provider's own "details" array rather than parsing the (already translated) summary text, see each *HealthCheckProvider's buildRow()/checkPage() for what "details" holds per kind. Each line also carries an optional direct link to the external tool's own report for this page (eg. the W3C validators, PageSpeed Insights, securityheaders.com) - none of these are called from here, they're just the public web UI for the same check already run
class PageHealthCheckAdviceBuilder implements HealthCheckAdviceProviderInterface
{
    // Same threshold PageSpeed/Lighthouse itself uses for its own green/orange split - a score in the orange band is still worth calling out, only red (<50) is truly urgent, but both get advice here since either falls short of "good"
    private const PAGESPEED_GOOD_THRESHOLD = 90;

    private const PAGESPEED_CATEGORY_LABELS = [
        'performance' => 'label.health_check_gauge_performance',
        'accessibility' => 'label.health_check_gauge_accessibility',
        'best-practices' => 'label.health_check_gauge_best_practices',
        'seo' => 'label.health_check_gauge_seo',
    ];

    public function __construct(
        private readonly TranslatorInterface $translator,
        private readonly ?LoggerInterface $logger = null,
    ) {
    }

    // Grouped by kind rather than a flat list - the Health check panel renders each kind's advice inside that row's own "Résumé" cell (see page_crud_form_theme.html.twig), not as one aggregate block
    public function buildAdvice(array $results): array
    {
        $advice = [];
        foreach ($results as $result) {
            $lines = match ($result->getKind()) {
                'pagespeed' => $this->pagespeedAdvice($result),
                'security-headers' => $this->securityHeadersAdvice($result),
                'w3c-html' => $this->w3cHtmlAdvice($result),
                'w3c-css' => $this->w3cCssAdvice($result),
                'content-quality' => $this->contentQualityAdvice($result),
                'ssl-certificate' => $this->sslCertificateAdvice($result),
                'mixed-content' => $this->mixedContentAdvice($result),
                default => $this->unknownKindAdvice($result),
            };
            if ($lines) {
                $advice[$result->getKind()] = $lines;
            }
        }

        return $advice;
    }

    // Still no advice line for a kind this builder doesn't know about - the Health check panel has no guidance text to show either way. Only a log line, so a newly-registered HealthCheckProviderInterface kind (this bundle's own future addition, or another bundle's) doesn't silently show results with no advice and no visible sign anything is missing
    private function unknownKindAdvice(HealthCheckResult $result): array
    {
        $this->logger?->warning(sprintf('No advice mapped for health check kind "%s" - add a case to PageHealthCheckAdviceBuilder::build().', $result->getKind()), ['kind' => $result->getKind()]);

        return [];
    }

    private function line(string $translationId, array $params, ?string $url): array
    {
        return ['text' => $this->translator->trans($translationId, $params, 'site'), 'url' => $url];
    }

    private function pagespeedAdvice(HealthCheckResult $result): array
    {
        $scores = $result->getDetails()['scores'] ?? [];
        $reportUrl = 'https://pagespeed.web.dev/report?url=' . rawurlencode($result->getUrl());

        $advice = [];
        foreach (self::PAGESPEED_CATEGORY_LABELS as $key => $labelId) {
            $score = $scores[$key] ?? null;
            if (null !== $score && $score < self::PAGESPEED_GOOD_THRESHOLD) {
                $advice[] = $this->line('label.health_check_advice_pagespeed', [
                    '%category%' => $this->translator->trans($labelId, [], 'config'),
                    '%score%' => $score,
                ], $reportUrl);
            }
        }

        return $advice;
    }

    private function securityHeadersAdvice(HealthCheckResult $result): array
    {
        $details = $result->getDetails() ?? [];
        $missing = $details['missing'] ?? [];
        $reportUrl = 'https://securityheaders.com/?q=' . rawurlencode($result->getUrl()) . '&followRedirects=on';

        $advice = [];
        if ($missing) {
            $advice[] = $this->line('label.health_check_advice_security_headers', ['%headers%' => implode(', ', $missing)], $reportUrl);
        }
        if ('*' === ($details['headers']['access-control-allow-origin'] ?? null)) {
            $advice[] = $this->line('label.health_check_advice_security_headers_cors', [], $reportUrl);
        }

        return $advice;
    }

    private function w3cHtmlAdvice(HealthCheckResult $result): array
    {
        $details = $result->getDetails() ?? [];
        $errors = \count($details['errors'] ?? []);
        $warnings = \count($details['warnings'] ?? []);
        $reportUrl = 'https://validator.w3.org/nu/?doc=' . rawurlencode($result->getUrl());

        $advice = [];
        if ($errors > 0) {
            $advice[] = $this->line('label.health_check_advice_w3c_html_errors', ['%count%' => $errors], $reportUrl);
        }
        if ($warnings > 0) {
            $advice[] = $this->line('label.health_check_advice_w3c_html_warnings', ['%count%' => $warnings], $reportUrl);
        }

        return $advice;
    }

    private function w3cCssAdvice(HealthCheckResult $result): array
    {
        $details = $result->getDetails() ?? [];
        $errors = \count($details['errors'] ?? []);
        $warnings = \count($details['warnings'] ?? []);
        $reportUrl = 'https://jigsaw.w3.org/css-validator/validator?uri=' . rawurlencode($result->getUrl()) . '&profile=css3svg';

        $advice = [];
        if ($errors > 0) {
            $advice[] = $this->line('label.health_check_advice_w3c_css_errors', ['%count%' => $errors], $reportUrl);
        }
        if ($warnings > 0) {
            $advice[] = $this->line('label.health_check_advice_w3c_css_warnings', ['%count%' => $warnings], $reportUrl);
        }

        return $advice;
    }

    // No external validator for this one - it's this bundle's own local check, there's no third-party report to link to
    private function contentQualityAdvice(HealthCheckResult $result): array
    {
        $details = $result->getDetails() ?? [];

        $advice = [];
        if (false === ($details['hasDescription'] ?? true)) {
            $advice[] = $this->line('label.health_check_advice_no_description', [], null);
        }
        if (false === ($details['hasH1'] ?? true)) {
            $advice[] = $this->line('label.health_check_advice_no_h1', [], null);
        }
        if (($details['imagesWithoutAlt'] ?? 0) > 0) {
            $advice[] = $this->line('label.health_check_advice_images_without_alt', ['%count%' => $details['imagesWithoutAlt']], null);
        }
        if ($details['brokenLinks'] ?? []) {
            $advice[] = $this->line('label.health_check_advice_broken_links', ['%count%' => \count($details['brokenLinks'])], null);
        }

        return $advice;
    }

    // No external validator for this one either - the certificate itself has no public report page to link to
    private function sslCertificateAdvice(HealthCheckResult $result): array
    {
        $daysLeft = $result->getDetails()['daysLeft'] ?? null;
        if (null === $daysLeft || $daysLeft > 30) {
            return [];
        }

        return [$this->line('label.health_check_advice_ssl_certificate', [], null)];
    }

    private function mixedContentAdvice(HealthCheckResult $result): array
    {
        $insecure = $result->getDetails()['insecureResources'] ?? [];
        if (!$insecure) {
            return [];
        }

        return [$this->line('label.health_check_advice_mixed_content', ['%count%' => \count($insecure)], null)];
    }
}
