<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests;

use c975L\UiBundle\Testing\ComponentCenteringAnalyzer;
use c975L\UiBundle\Testing\StylesheetCascade;
use PHPUnit\Framework\TestCase;

/**
 * The same analysis UiBundle runs over its own sheet, run here over the pair - which is where the collisions
 * actually are. This bundle's own page-wide "section { margin: 1em auto }" is what UiBundle's block reset was
 * written to cancel in the first place, and neither bundle can see that pair from inside itself.
 *
 * UiBundle's sheet loads after this one (see the order the pages print), so on equal specificity it wins.
 */
class ComponentCenteringCascadeTest extends TestCase
{
    // The two bundles' sheets in the order a page loads them, source order deciding between equal specificities
    public function testNoRuleOfEitherBundleClobbersTheOthersCentering(): void
    {
        if (!class_exists(ComponentCenteringAnalyzer::class)) {
            $this->markTestSkipped('The installed c975l/core-bundle predates UiBundle\'s Testing/ namespace, nothing to run the analysis with.');
        }

        $site = dirname(__DIR__);
        $ui = ScaffoldThemeTest::uiBundleDir();

        $analyzer = new ComponentCenteringAnalyzer(StylesheetCascade::fromFiles(
            $site . '/public/css/styles.css',
            $ui . '/public/css/styles.css'
        ));

        $result = $analyzer->analyse(ComponentCenteringAnalyzer::tagsByClass(
            $site . '/templates/components',
            $ui . '/templates/components'
        ));

        foreach ($result['violations'] as $violation) {
            self::fail(ComponentCenteringAnalyzer::describe($violation));
        }

        // Guards against silently passing on an empty set, should either sheet or either template tree stop being read
        self::assertGreaterThan(5, count($result['centered']), 'No centered component was found across the two bundles, the sheets or the templates are no longer being read.');
    }
}
