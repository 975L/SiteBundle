<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Management\SiteTemplateProvider;
use c975L\SiteBundle\Management\TemplateProviderInterface;
use PHPUnit\Framework\TestCase;

class SiteTemplateProviderTest extends TestCase
{
    public function testImplementsTemplateProviderInterface(): void
    {
        $this->assertInstanceOf(TemplateProviderInterface::class, new SiteTemplateProvider());
    }

    // Reads every config/templates/*.json shipped by the bundle into one template per file, keyed by filename
    public function testGetTemplatesReturnsOneEntryPerJsonFile(): void
    {
        $files = glob(\dirname(__DIR__, 2) . '/config/templates/*.json') ?: [];

        $templates = (new SiteTemplateProvider())->getTemplates();

        $this->assertCount(\count($files), $templates);
        $this->assertArrayHasKey('agency-home', $templates);
        $this->assertSame('label.template_agency_home', $templates['agency-home']['label']);
        $this->assertSame('site', $templates['agency-home']['domain']);
        $this->assertSame('hero', $templates['agency-home']['blocks'][0]['kind']);
        $this->assertCount(7, $templates['agency-home']['blocks']);
    }
}
