<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\DependencyInjection\Compiler;

use c975L\ConfigBundle\Management\SitemapProviderInterface;
use c975L\SiteBundle\DependencyInjection\Compiler\DeclaredUrlsHealthCheckPass;
use c975L\SiteBundle\Management\ContentQualityAnalyzer;
use c975L\SiteBundle\Management\DeclaredUrlsHealthCheckProvider;
use c975L\SiteBundle\Management\SitePageSitemapProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\Argument\TaggedIteratorArgument;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;

class DeclaredUrlsHealthCheckPassTest extends TestCase
{
    private function createContainer(bool $withAnalyzer = true): ContainerBuilder
    {
        $container = new ContainerBuilder();
        if ($withAnalyzer) {
            $container->setDefinition(ContentQualityAnalyzer::class, new Definition(ContentQualityAnalyzer::class));
        }

        return $container;
    }

    private function healthCheckDefinitions(ContainerBuilder $container): array
    {
        return array_filter(
            $container->getDefinitions(),
            static fn (Definition $definition) => DeclaredUrlsHealthCheckProvider::class === $definition->getClass(),
        );
    }

    // Declaring a sitemap is all it takes: no health check class to write in BookBundle/ShopBundle/GalleryBundle/CrowdfundingBundle
    public function testProcessRegistersOneProviderPerSitemapProvider(): void
    {
        $container = $this->createContainer();
        $container->setDefinition('book.sitemap', new Definition(FakeBookSitemapProvider::class));
        $container->setDefinition('shop.sitemap', new Definition(FakeShopSitemapProvider::class));

        (new DeclaredUrlsHealthCheckPass())->process($container);

        $definitions = $this->healthCheckDefinitions($container);
        $this->assertCount(2, $definitions);
        foreach ($definitions as $definition) {
            $this->assertTrue($definition->hasTag('c975l.health_check_provider'));
        }
    }

    // SiteBundle's own pages are already checked, in more detail, by ContentQualityHealthCheckProvider
    public function testProcessSkipsTheSitePageSitemapProvider(): void
    {
        $container = $this->createContainer();
        $container->setDefinition('site.sitemap', new Definition(SitePageSitemapProvider::class));

        (new DeclaredUrlsHealthCheckPass())->process($container);

        $this->assertSame([], $this->healthCheckDefinitions($container));
    }

    public function testProcessIgnoresAServiceThatIsNotASitemapProvider(): void
    {
        $container = $this->createContainer();
        $container->setDefinition('some.service', new Definition(\stdClass::class));

        (new DeclaredUrlsHealthCheckPass())->process($container);

        $this->assertSame([], $this->healthCheckDefinitions($container));
    }

    // A definition can carry no class at all (an alias-like or abstract one) - not a reason to blow up the whole compilation
    public function testProcessIgnoresAClasslessDefinition(): void
    {
        $container = $this->createContainer();
        $container->setDefinition('classless', new Definition());

        (new DeclaredUrlsHealthCheckPass())->process($container);

        $this->assertSame([], $this->healthCheckDefinitions($container));
    }

    // Nothing to build a provider with if SiteBundle's own analyzer isn't there
    public function testProcessDoesNothingWithoutTheAnalyzer(): void
    {
        $container = $this->createContainer(withAnalyzer: false);
        $container->setDefinition('book.sitemap', new Definition(FakeBookSitemapProvider::class));

        (new DeclaredUrlsHealthCheckPass())->process($container);

        $this->assertSame([], $this->healthCheckDefinitions($container));
    }

    // An abstract definition is a template for other services, not a service: referencing it would fail the whole container's compilation
    public function testProcessSkipsAnAbstractDefinition(): void
    {
        $container = $this->createContainer();
        $container->setDefinition('book.sitemap.abstract', (new Definition(FakeBookSitemapProvider::class))->setAbstract(true));

        (new DeclaredUrlsHealthCheckPass())->process($container);

        $this->assertSame([], $this->healthCheckDefinitions($container));
    }

    // A synthetic service is injected at runtime and may never be set at all - no better a thing to health-check
    public function testProcessSkipsASyntheticDefinition(): void
    {
        $container = $this->createContainer();
        $container->setDefinition('book.sitemap.synthetic', (new Definition(FakeBookSitemapProvider::class))->setSynthetic(true));

        (new DeclaredUrlsHealthCheckPass())->process($container);

        $this->assertSame([], $this->healthCheckDefinitions($container));
    }

    // The generated providers must survive a real compilation, which is where an invalid reference would surface - collected here the same way ConfigBundle's HealthCheckRunner collects them, so they aren't removed as unused before anything gets checked
    public function testTheGeneratedProvidersCompile(): void
    {
        $container = $this->createContainer();
        $container->setDefinition('book.sitemap', new Definition(FakeBookSitemapProvider::class));
        $container->setDefinition('book.sitemap.abstract', (new Definition(FakeBookSitemapProvider::class))->setAbstract(true));
        $container->setDefinition('health_check_runner', (new Definition(\ArrayObject::class))
            ->setPublic(true)
            ->setArguments([new TaggedIteratorArgument('c975l.health_check_provider')]));

        (new DeclaredUrlsHealthCheckPass())->process($container);
        $container->compile();

        $this->assertCount(1, $this->healthCheckDefinitions($container));
    }

    // Each generated service gets its own id, so a second bundle's provider doesn't overwrite the first one's
    public function testProcessGivesEachGeneratedServiceItsOwnId(): void
    {
        $container = $this->createContainer();
        $container->setDefinition('book.sitemap', new Definition(FakeBookSitemapProvider::class));
        $container->setDefinition('shop.sitemap', new Definition(FakeShopSitemapProvider::class));

        (new DeclaredUrlsHealthCheckPass())->process($container);

        $this->assertCount(2, array_unique(array_keys($this->healthCheckDefinitions($container))));
    }
}

class FakeBookSitemapProvider implements SitemapProviderInterface
{
    public function getSitemapName(): string
    {
        return 'book';
    }

    public function getUrls(): array
    {
        return [];
    }
}

class FakeShopSitemapProvider implements SitemapProviderInterface
{
    public function getSitemapName(): string
    {
        return 'shop';
    }

    public function getUrls(): array
    {
        return [];
    }
}
