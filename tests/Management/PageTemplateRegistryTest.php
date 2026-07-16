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
use c975L\SiteBundle\Management\PageTemplateRegistry;
use PHPUnit\Framework\TestCase;

class PageTemplateRegistryTest extends TestCase
{
    private function createProvider(array $templates): PageTemplateProviderInterface
    {
        $provider = $this->createStub(PageTemplateProviderInterface::class);
        $provider->method('getTemplates')->willReturn($templates);

        return $provider;
    }

    public function testHasAndGetReflectTemplatesMergedAcrossProviders(): void
    {
        $providerA = $this->createProvider(['agency-home-warm' => ['label' => 'label.a', 'blocks' => [['kind' => 'hero', 'data' => []]]]]);
        $providerB = $this->createProvider(['shop-landing' => ['label' => 'label.b', 'blocks' => [['kind' => 'products', 'data' => []]]]]);
        $registry = new PageTemplateRegistry([$providerA, $providerB]);

        $this->assertTrue($registry->has('agency-home-warm'));
        $this->assertTrue($registry->has('shop-landing'));
        $this->assertSame(['label' => 'label.a', 'blocks' => [['kind' => 'hero', 'data' => []]]], $registry->get('agency-home-warm'));
    }

    public function testHasReturnsFalseAndGetReturnsNullForUnknownTemplate(): void
    {
        $registry = new PageTemplateRegistry([$this->createProvider([])]);

        $this->assertFalse($registry->has('unknown'));
        $this->assertNull($registry->get('unknown'));
    }

    public function testAllReturnsEveryMergedTemplate(): void
    {
        $providerA = $this->createProvider(['template-a' => ['label' => 'a', 'blocks' => []]]);
        $providerB = $this->createProvider(['template-b' => ['label' => 'b', 'blocks' => []]]);
        $registry = new PageTemplateRegistry([$providerA, $providerB]);

        $this->assertSame([
            'template-a' => ['label' => 'a', 'blocks' => []],
            'template-b' => ['label' => 'b', 'blocks' => []],
        ], $registry->all());
    }
}
