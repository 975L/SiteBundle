<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\DependencyInjection\Compiler;

use c975L\ConfigBundle\Management\SitemapProviderInterface;
use c975L\SiteBundle\Management\ContentQualityAnalyzer;
use c975L\SiteBundle\Management\DeclaredUrlsHealthCheckProvider;
use c975L\SiteBundle\Management\SitePageSitemapProvider;
use Symfony\Component\DependencyInjection\Compiler\CompilerPassInterface;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Definition;
use Symfony\Component\DependencyInjection\Reference;

// Registers one DeclaredUrlsHealthCheckProvider per SitemapProviderInterface found in the container, so a bundle declaring a sitemap is health-checked without implementing anything else - BookBundle, ShopBundle, GalleryBundle and CrowdfundingBundle get their books/products/photos/campaigns checked with no line of code of their own. Services are discovered by interface rather than by ConfigBundle's own tag, so this doesn't depend on which bundle's compiler pass ran first
class DeclaredUrlsHealthCheckPass implements CompilerPassInterface
{
    public function process(ContainerBuilder $container): void
    {
        if (!$container->has(ContentQualityAnalyzer::class)) {
            return;
        }

        foreach ($container->getDefinitions() as $id => $definition) {
            // An abstract definition is a template for other services, not a service - referencing one fails the container's compilation outright. A synthetic one is injected at runtime and may never be set at all, which is no better a thing to health-check. ConfigBundle's TaggedInterfacePass needs neither guard because it only tags what it finds, where this pass builds a Reference to it
            if ($definition->isAbstract() || $definition->isSynthetic()) {
                continue;
            }

            $class = $definition->getClass();
            if (!$class || !$this->isDeclaredUrlSource($class)) {
                continue;
            }

            $container->setDefinition(
                'c975l.site.declared_urls_health_check.' . $this->suffix($class),
                (new Definition(DeclaredUrlsHealthCheckProvider::class))
                    ->setArguments([new Reference($id), new Reference(ContentQualityAnalyzer::class)])
                    ->addTag('c975l.health_check_provider'),
            );
        }
    }

    // SiteBundle's own pages are deliberately left out: ContentQualityHealthCheckProvider already checks every one of them, and does it better (each offence traced back to the block holding it, each row linking to the page's own edit screen)
    private function isDeclaredUrlSource(string $class): bool
    {
        if (SitePageSitemapProvider::class === $class) {
            return false;
        }

        try {
            // Same guard as ConfigBundle's TaggedInterfacePass: a vendor service can reference a class whose interfaces come from a require-dev-only package, absent in prod
            return is_subclass_of($class, SitemapProviderInterface::class);
        } catch (\Throwable) {
            return false;
        }
    }

    // Only used to keep the generated service ids apart and readable - the kind the dashboard shows comes from the sitemap provider itself, at runtime (see DeclaredUrlsHealthCheckProvider::getKind())
    private function suffix(string $class): string
    {
        return strtolower(str_replace('\\', '_', $class));
    }
}
