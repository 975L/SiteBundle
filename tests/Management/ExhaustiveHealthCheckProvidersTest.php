<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\ConfigBundle\Management\HealthCheckExhaustiveInterface;
use c975L\SiteBundle\Management\ContentQualityHealthCheckProvider;
use c975L\SiteBundle\Management\MixedContentHealthCheckProvider;
use c975L\SiteBundle\Management\SitePageHealthCheckProvider;
use c975L\SiteBundle\Management\TranslationHealthCheckProvider;
use c975L\SiteBundle\Management\W3cCssHealthCheckProvider;
use c975L\SiteBundle\Management\W3cHtmlHealthCheckProvider;
use PHPUnit\Framework\TestCase;

// Every provider listing the site's pages enumerates its whole domain on each run, so HealthCheckRunner may drop the rows of a url the run no longer returns. Without that marker, a page moved to the bin - which PageRepository::findAllOrdered() stops returning - keeps its last verdict as the dashboard's current state for good, dated by the run that never checked it
class ExhaustiveHealthCheckProvidersTest extends TestCase
{
    public function testEveryPageProviderIsExhaustive(): void
    {
        $providers = [
            W3cHtmlHealthCheckProvider::class,
            W3cCssHealthCheckProvider::class,
            ContentQualityHealthCheckProvider::class,
            MixedContentHealthCheckProvider::class,
            SitePageHealthCheckProvider::class,
            TranslationHealthCheckProvider::class,
        ];

        foreach ($providers as $provider) {
            $this->assertTrue(is_subclass_of($provider, HealthCheckExhaustiveInterface::class), $provider);
        }
    }
}
