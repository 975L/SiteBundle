<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Twig;

use c975L\ConfigBundle\Entity\HealthCheckResult;
use c975L\ConfigBundle\Management\HealthCheckAdviceBuilder;
use c975L\ConfigBundle\Repository\HealthCheckResultRepository;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

// Backs the Page CRUD edit screen's "Health check" tab (see PageHealthCheckPanelType/its form theme block) - a Twig function rather than a controller-computed variable, since EasyAdmin's edit form context doesn't otherwise expose custom per-field data
class PageHealthCheckExtension extends AbstractExtension
{
    public function __construct(
        private readonly PagePublicUrlResolver $pagePublicUrlResolver,
        private readonly HealthCheckResultRepository $healthCheckResultRepository,
        // ConfigBundle's own aggregator (merges every registered HealthCheckAdviceProviderInterface, not just this bundle's PageHealthCheckAdviceBuilder) - so this tab and the dashboard "Health check" page always read the same advice, see HealthCheckController::index()
        private readonly HealthCheckAdviceBuilder $healthCheckAdviceBuilder,
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('page_health_check', [$this, 'getPanel']),
        ];
    }

    // ['results' => HealthCheckResult[], 'advice' => string[]] - empty results (and no advice) when "site-url" isn't configured yet, same as every HealthCheckProviderInterface implementation
    public function getPanel(Page $page): array
    {
        $url = $this->pagePublicUrlResolver->resolve($page);
        if (null === $url) {
            return ['results' => [], 'advice' => []];
        }

        // "security-headers" is site-wide (checked once for the whole site, always stored under the homepage's own url - see SecurityHeadersHealthCheckProvider) and already shown in ConfigBundle's dashboard "Site" section; it would only ever surface here when editing the homepage itself, so it's dropped to avoid showing the same result twice
        $results = array_values(array_filter(
            $this->healthCheckResultRepository->findLatestByUrl($url),
            static fn (HealthCheckResult $result) => 'security-headers' !== $result->getKind(),
        ));

        return [
            'results' => $results,
            'advice' => $this->healthCheckAdviceBuilder->build($results),
        ];
    }
}
