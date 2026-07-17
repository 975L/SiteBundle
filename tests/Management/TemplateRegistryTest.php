<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Management\TemplateProviderInterface;
use c975L\SiteBundle\Management\TemplateRegistry;
use PHPUnit\Framework\TestCase;

class TemplateRegistryTest extends TestCase
{
    private function createProvider(array $templates): TemplateProviderInterface
    {
        $provider = $this->createStub(TemplateProviderInterface::class);
        $provider->method('getTemplates')->willReturn($templates);

        return $provider;
    }

    public function testHasAndGetReflectTemplatesMergedAcrossProviders(): void
    {
        $providerA = $this->createProvider(['agency-home' => ['label' => 'label.a', 'blocks' => [['kind' => 'hero', 'data' => []]]]]);
        $providerB = $this->createProvider(['shop-landing' => ['label' => 'label.b', 'blocks' => [['kind' => 'products', 'data' => []]]]]);
        $registry = new TemplateRegistry([$providerA, $providerB]);

        $this->assertTrue($registry->has('agency-home'));
        $this->assertTrue($registry->has('shop-landing'));
        $this->assertSame(['label' => 'label.a', 'blocks' => [['kind' => 'hero', 'data' => []]]], $registry->get('agency-home'));
    }

    public function testHasReturnsFalseAndGetReturnsNullForUnknownTemplate(): void
    {
        $registry = new TemplateRegistry([$this->createProvider([])]);

        $this->assertFalse($registry->has('unknown'));
        $this->assertNull($registry->get('unknown'));
    }

    public function testAllReturnsEveryMergedTemplate(): void
    {
        $providerA = $this->createProvider(['template-a' => ['label' => 'a', 'blocks' => []]]);
        $providerB = $this->createProvider(['template-b' => ['label' => 'b', 'blocks' => []]]);
        $registry = new TemplateRegistry([$providerA, $providerB]);

        $this->assertSame([
            'template-a' => ['label' => 'a', 'blocks' => []],
            'template-b' => ['label' => 'b', 'blocks' => []],
        ], $registry->all());
    }
}
