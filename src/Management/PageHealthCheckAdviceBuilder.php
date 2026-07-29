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
use c975L\ConfigBundle\Management\HealthCheckAdviceBuilder;
use c975L\ConfigBundle\Management\HealthCheckAdviceProviderInterface;
use Psr\Log\LoggerInterface;
use Symfony\Contracts\Translation\TranslatorInterface;

// Turns a page's own HealthCheckResult rows into short, actionable advice lines - the Health check panel already shows *what* was found (via the shared table/gauges), this says *what to do about it*. Reads each provider's own "details" array rather than parsing the (already translated) summary text, see each *HealthCheckProvider's buildRow()/checkPage() for what "details" holds per kind. Each line also carries an optional direct link to the external tool's own report for this page (eg. the W3C validators, PageSpeed Insights, securityheaders.com) - none of these are called from here, they're just the public web UI for the same check already run
class PageHealthCheckAdviceBuilder implements HealthCheckAdviceProviderInterface
{
    // Kinds another bundle already writes advice for through its own HealthCheckAdviceProvider (here ConfigBundle's BackupHealthCheckAdviceProvider) - skipped silently rather than logged as unmapped, unknownKindAdvice() being there to catch a kind *nobody* advises on, not one this bundle simply has no business advising on
    private const KINDS_ADVISED_ELSEWHERE = ['backup'];

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

    // Keyed per result (HealthCheckAdviceBuilder::key(), ie. kind + url) rather than by kind alone - the Health check panel renders each result's advice inside that row's own "Résumé" cell (see page_crud_form_theme.html.twig), and the dashboard's own page lists one row per page *and* per kind, so keying by kind had every page's content-quality row showing the last checked page's advice
    public function buildAdvice(array $results): array
    {
        $advice = [];
        foreach ($results as $result) {
            $lines = match (true) {
                'pagespeed' === $result->getKind() => $this->pagespeedAdvice($result),
                'security-headers' === $result->getKind() => $this->securityHeadersAdvice($result),
                'w3c-html' === $result->getKind() => $this->w3cHtmlAdvice($result),
                'w3c-css' === $result->getKind() => $this->w3cCssAdvice($result),
                // Every "urls-<bundle>" kind holds exactly the same details as content-quality - same analyzer behind both, only the url list differs (see DeclaredUrlsHealthCheckProvider)
                'content-quality' === $result->getKind(), str_starts_with($result->getKind(), 'urls-') => $this->contentQualityAdvice($result),
                'ssl-certificate' === $result->getKind() => $this->sslCertificateAdvice($result),
                'mixed-content' === $result->getKind() => $this->mixedContentAdvice($result),
                'deployment' === $result->getKind() => $this->deploymentAdvice($result),
                \in_array($result->getKind(), self::KINDS_ADVISED_ELSEWHERE, true) => [],
                default => $this->unknownKindAdvice($result),
            };
            if ($lines) {
                $advice[HealthCheckAdviceBuilder::key($result)] = $lines;
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

    // $items: the individual offenders behind a "%count% of them" advice line (see contentQualityAdvice()), rendered by the shared table as a collapsed list under the line rather than as more advice lines - a page with a dozen images without alt text would otherwise bury every other row of the table
    private function line(string $translationId, array $params, ?string $url, array $items = []): array
    {
        return ['text' => $this->translator->trans($translationId, $params, 'site'), 'url' => $url, 'items' => $items];
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

    // No external validator for this one - it's this bundle's own local check, there's no third-party report to link to. Each image/link is listed individually under its advice line instead, linking to the block holding it (see ContentQualityAnalyzer::describeImages()/describeLinks())
    private function contentQualityAdvice(HealthCheckResult $result): array
    {
        $details = $result->getDetails() ?? [];

        return array_merge(
            $this->redirectAdvice($details),
            $this->titleAdvice($details),
            $this->descriptionAdvice($details),
            $this->headingAdvice($details),
            $this->socialTagsAdvice($details),
            $this->offendersAdvice($details),
        );
    }

    // First of the lines, like the summary's own clause: everything below it was measured on the url the redirect landed on, not on the one declared
    private function redirectAdvice(array $details): array
    {
        $redirect = $details['redirect'] ?? null;

        return null === $redirect ? [] : [$this->line('label.health_check_advice_redirects', ['%url%' => $redirect['finalUrl'] ?? ''], null)];
    }

    // The thresholds themselves come from ContentQualityAnalyzer, which already applied them - repeating them here is only to tell the user what to aim for
    private function titleAdvice(array $details): array
    {
        $issue = $details['titleIssue'] ?? null;
        if ('missing' === $issue) {
            return [$this->line('label.health_check_advice_no_title', [], null)];
        }
        if (!\in_array($issue, ['short', 'long'], true)) {
            return [];
        }

        return [$this->line('label.health_check_advice_title_length', [
            '%length%' => $details['titleLength'] ?? 0,
            '%min%' => ContentQualityAnalyzer::TITLE_MIN_LENGTH,
            '%max%' => ContentQualityAnalyzer::TITLE_MAX_LENGTH,
        ], null)];
    }

    // A description that isn't there at all gets "add one" rather than "make it longer", same split as the summary's own
    private function descriptionAdvice(array $details): array
    {
        if (false === ($details['hasDescription'] ?? true)) {
            return [$this->line('label.health_check_advice_no_description', [], null)];
        }
        if (!\in_array($details['descriptionIssue'] ?? null, ['short', 'long'], true)) {
            return [];
        }

        return [$this->line('label.health_check_advice_description_length', [
            '%length%' => $details['descriptionLength'] ?? 0,
            '%min%' => ContentQualityAnalyzer::DESCRIPTION_MIN_LENGTH,
            '%max%' => ContentQualityAnalyzer::DESCRIPTION_MAX_LENGTH,
        ], null)];
    }

    // "hasH1" is read as a fallback for the rows persisted before the check counted them, whose details hold that key alone - they get the same advice until the next run replaces them
    private function headingAdvice(array $details): array
    {
        $count = $details['h1Count'] ?? (($details['hasH1'] ?? true) ? 1 : 0);

        return match (true) {
            0 === $count => [$this->line('label.health_check_advice_no_h1', [], null)],
            $count > 1 => [$this->line('label.health_check_advice_several_h1', [], null)],
            default => [],
        };
    }

    // Listed by name rather than counted - "og:description, og:image" is the whole fix, there's nothing to expand into a collapsed list here
    private function socialTagsAdvice(array $details): array
    {
        $tags = array_filter((array) ($details['missingSocialTags'] ?? []), '\is_string');

        return $tags ? [$this->line('label.health_check_advice_social_tags', ['%tags%' => implode(', ', $tags)], null)] : [];
    }

    // The three details holding a list of individual offenders, each rendered as one "%count% of them" line expandable into that list. Broken external links are worded as "check them" rather than "fix them": the page they point at may well be back tomorrow, and the fix (drop the link, point it elsewhere) is a call only the author can make
    private function offendersAdvice(array $details): array
    {
        $offenders = [
            'imagesWithoutAlt' => ['label.health_check_advice_images_without_alt', static fn (array $image): string => $image['src'] ?? ''],
            'brokenLinks' => ['label.health_check_advice_broken_links', $this->linkText(...)],
            'brokenExternalLinks' => ['label.health_check_advice_broken_external_links', $this->linkText(...)],
        ];

        $advice = [];
        foreach ($offenders as $key => [$translationId, $text]) {
            // Only ever a list here, but a result persisted before these details became lists of their own still holds a plain count - normalized away rather than crashing the whole panel until the next run
            $items = $this->itemList($details[$key] ?? []);
            if ($items) {
                $advice[] = $this->line($translationId, ['%count%' => \count($items)], null, array_map(
                    fn (array $item): array => $this->item($text($item), $item),
                    $items,
                ));
            }
        }

        return $advice;
    }

    // What the visitor actually clicks on, ahead of the url - an image-only link (or one whose text couldn't be read) falls back to the url alone
    private function linkText(array $link): string
    {
        $url = $link['url'] ?? '';

        return ($link['text'] ?? null) ? $link['text'] . ' - ' . $url : $url;
    }

    // Anything that isn't a list of detail arrays (an older result's plain count, a null) yields no items rather than a broken list
    private function itemList(mixed $details): array
    {
        return \is_array($details) ? array_values(array_filter($details, '\is_array')) : [];
    }

    // The block's own label as the link text, so the user knows where they'll land - a block-less image/link (one coming from the theme, the menu, a template) has no link at all, only its own text
    private function item(string $text, array $details): array
    {
        return [
            'text' => $text,
            'url' => $details['editUrl'] ?? null,
            'label' => ($details['block'] ?? null) ? $this->translator->trans('label.health_check_advice_edit_block', ['%block%' => $details['block']], 'site') : null,
        ];
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

    // Each of these is a server/routing fix rather than a content one, so the advice points at what to change, not at a block to edit
    private function deploymentAdvice(HealthCheckResult $result): array
    {
        return match ($result->getDetails()['issue'] ?? null) {
            'https-redirect' => [$this->line('label.health_check_advice_https_redirect', [], null)],
            'insecure-redirect' => [$this->line('label.health_check_advice_https_redirect_insecure', [], null)],
            'not-404' => [$this->line('label.health_check_advice_not_found_status', ['%status%' => $result->getDetails()['statusCode'] ?? ''], null)],
            'default-404' => [$this->line('label.health_check_advice_not_found_default_page', [], null)],
            default => [],
        };
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
