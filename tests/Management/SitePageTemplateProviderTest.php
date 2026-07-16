<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Management\PageTemplateProviderInterface;
use c975L\SiteBundle\Management\SitePageTemplateProvider;
use PHPUnit\Framework\TestCase;

class SitePageTemplateProviderTest extends TestCase
{
    public function testImplementsPageTemplateProviderInterface(): void
    {
        $this->assertInstanceOf(PageTemplateProviderInterface::class, new SitePageTemplateProvider());
    }

    // Reads every config/page-templates/*.json shipped by the bundle into one template per file,
    // keyed by filename
    public function testGetTemplatesReturnsOneEntryPerJsonFile(): void
    {
        $files = glob(\dirname(__DIR__, 2) . '/config/page-templates/*.json') ?: [];

        $templates = (new SitePageTemplateProvider())->getTemplates();

        $this->assertCount(\count($files), $templates);
        $this->assertArrayHasKey('agency-home-warm', $templates);
        $this->assertSame('label.page_template_agency_home_warm', $templates['agency-home-warm']['label']);
        $this->assertSame('site', $templates['agency-home-warm']['domain']);
        $this->assertSame('hero', $templates['agency-home-warm']['blocks'][0]['kind']);
        $this->assertCount(7, $templates['agency-home-warm']['blocks']);
    }
}
