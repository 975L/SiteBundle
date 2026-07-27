<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\HealthCheckProviderInterface;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PageEditUrlResolver;
use c975L\SiteBundle\Service\PagePublicUrlResolver;

// Runs the content-quality checks (see ContentQualityAnalyzer, which does the actual work and is shared with DeclaredUrlsHealthCheckProvider) over this bundle's own published pages. Only the url list is this class's business: each Page is carried along with its url so every offence found in the rendered html can be traced back to the block holding it
class ContentQualityHealthCheckProvider implements HealthCheckProviderInterface
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PagePublicUrlResolver $pagePublicUrlResolver,
        private readonly PageEditUrlResolver $pageEditUrlResolver,
        private readonly ContentQualityAnalyzer $contentQualityAnalyzer,
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

            $pages[] = ['url' => $url, 'label' => $page->getTitle(), 'editUrl' => $this->pageEditUrlResolver->resolve($page), 'page' => $page];
        }

        return $this->contentQualityAnalyzer->analyze($pages);
    }
}
