<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\ContentQualityAnalyzer;
use c975L\ConfigBundle\Management\HealthCheckExhaustiveInterface;

// Runs the content-quality checks (see ConfigBundle's ContentQualityAnalyzer, which does the actual work and is shared with DeclaredUrlsHealthCheckProvider) over this bundle's own published pages. Only the url list is this class's business: each page is carried along with its url as the entry's 'source', with the language it was read in (see PageLocaleSource), which PageContentOffenceLocator turns back into the block holding each offence found in the rendered html
class ContentQualityHealthCheckProvider implements HealthCheckExhaustiveInterface
{
    public function __construct(
        private readonly ContentQualityAnalyzer $contentQualityAnalyzer,
        private readonly PageHealthCheckTargets $targets,
    ) {
    }

    public function getKind(): string
    {
        return 'content-quality';
    }

    public function runChecks(): array
    {
        $pages = [];
        // One entry per page and per language it was written in: read in another language a page has another title, another prose and another set of links, so it is another thing to check (see PageHealthCheckTargets)
        foreach ($this->targets->all() as $target) {
            // 'indexable' carries what the Page itself declares, and is what lets the analyzer report a page asking crawlers to drop a url the sitemap declares (see ContentQualityAnalyzer): every published page is checked here, including the ones deliberately kept out of search engines (the account ones), and those carry their noindex on purpose - reporting it would leave them red forever with nothing to fix
            $pages[] = [
                'url' => $target['url'],
                'label' => $target['label'],
                'editUrl' => $target['editUrl'],
                // The page and the language it was read in: the offences found in it are corrected on that language's screen (see PageLocaleSource)
                'source' => new PageLocaleSource($target['page'], $target['locale']),
                'indexable' => $target['page']->isIndexable(),
            ];
        }

        return $this->contentQualityAnalyzer->analyze($pages);
    }
}
