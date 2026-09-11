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
use c975L\ConfigBundle\Management\HealthCheckExhaustiveInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\UrlStatusChecker;
use c975L\SiteBundle\Service\MixedContentClient;
use Symfony\Contracts\Translation\TranslatorInterface;

// Flags http:// resources (images, scripts, stylesheets...) loaded from an https:// page - browsers block or warn on these ("mixed content"). Only meaningful once the site itself is served over https, skipped entirely otherwise (see runChecks())
class MixedContentHealthCheckProvider implements HealthCheckExhaustiveInterface
{
    public function __construct(
        private readonly MixedContentClient $mixedContentClient,
        private readonly PageHealthCheckTargets $targets,
        private readonly UrlStatusChecker $urlStatusChecker,
        private readonly ConfigServiceInterface $configService,
        private readonly TranslatorInterface $translator,
    ) {
    }

    public function getKind(): string
    {
        return 'mixed-content';
    }

    public function runChecks(): array
    {
        // Same reason as the throw below: a site url that is not https is a configuration this check cannot run against, not a site whose stored rows have all become stale
        if (!str_starts_with((string) $this->configService->get('site-url'), 'https://')) {
            throw new \RuntimeException('Site url is not https: mixed content cannot be checked.');
        }

        $results = [];
        // One row per page and per language it was written in (see PageHealthCheckTargets)
        foreach ($this->targets->all() as $target) {
            $results[] = $this->checkPage($target['url'], $target['label'], $target['editUrl']);
        }

        return $results;
    }

    private function checkPage(string $url, ?string $label, ?string $editUrl): array
    {
        if (!$this->urlStatusChecker->exists($url)) {
            return [
                'url' => $url,
                'label' => $label,
                'status' => HealthCheckResult::STATUS_SKIPPED,
                'summary' => $this->translator->trans('label.health_check_page_not_found', [], 'site'),
                'details' => [],
                'editUrl' => $editUrl,
            ];
        }

        try {
            $insecure = $this->mixedContentClient->findInsecureResources($url);
        } catch (\Throwable $e) {
            return [
                'url' => $url,
                'label' => $label,
                'status' => HealthCheckResult::STATUS_ERROR,
                'summary' => $this->translator->trans('label.health_check_mixed_content_call_failed', ['%message%' => $e->getMessage()], 'site'),
                'details' => ['error' => $e->getMessage()],
                'editUrl' => $editUrl,
            ];
        }

        return [
            'url' => $url,
            'label' => $label,
            'status' => $insecure ? HealthCheckResult::STATUS_ERROR : HealthCheckResult::STATUS_OK,
            'summary' => $insecure
                ? $this->translator->trans('label.health_check_mixed_content_found', ['%count%' => \count($insecure)], 'site')
                : $this->translator->trans('label.health_check_mixed_content_ok', [], 'site'),
            'details' => ['insecureResources' => $insecure],
            'editUrl' => $editUrl,
        ];
    }
}
