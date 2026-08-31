<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests;

use c975L\SiteBundle\c975LSiteBundle;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Config\FileLocator;
use Symfony\Component\Config\Loader\LoaderResolver;
use Symfony\Component\DependencyInjection\ContainerBuilder;
use Symfony\Component\DependencyInjection\Loader\Configurator\ContainerConfigurator;
use Symfony\Component\DependencyInjection\Loader\PhpFileLoader;
use Symfony\Component\DependencyInjection\Loader\YamlFileLoader;

// What "/{_locale}/pages/{page}" accepts between its slashes - the languages a site declares beside the one it is written in
class LocalesPatternTest extends TestCase
{
    /**
     * @param list<string> $enabledLocales
     */
    private function pattern(array $enabledLocales, string $defaultLocale): string
    {
        $builder = new ContainerBuilder();
        $builder->setParameter('kernel.enabled_locales', $enabledLocales);
        $builder->setParameter('kernel.default_locale', $defaultLocale);
        $instanceof = [];

        // A real configurator rather than a stub: ContainerConfigurator::import() is final, so the services file is loaded for good
        $file = \dirname(__DIR__) . '/src/c975LSiteBundle.php';
        $locator = new FileLocator(\dirname($file));
        $loader = new PhpFileLoader($builder, $locator);
        $loader->setResolver(new LoaderResolver([new YamlFileLoader($builder, $locator), $loader]));
        new c975LSiteBundle()->loadExtension([], new ContainerConfigurator($builder, $loader, $instanceof, $file, $file), $builder);

        return (string) $builder->getParameter('c975l_site.locales_pattern');
    }

    // The writing language keeps its bare urls, the ones the sitemap and the hreflang groups declare: prefixing it too would answer the same page under a second url
    public function testTheWritingLanguageIsLeftOutOfThePattern(): void
    {
        $this->assertSame('en|es', $this->pattern(['fr', 'en', 'es'], 'fr'));
    }

    // Whatever the site is written in, and wherever it sits in the declared list
    public function testTheWritingLanguageIsLeftOutWhicheverItIs(): void
    {
        $this->assertSame('fr|en', $this->pattern(['fr', 'en', 'es'], 'es'));
    }

    // A site declaring a single language - every c975L site until it says otherwise - gets a pattern matching nothing, so the localised routes exist without ever answering
    public function testASingleLanguageMatchesNothing(): void
    {
        $this->assertSame('(?!)', $this->pattern(['fr'], 'fr'));
        $this->assertSame('(?!)', $this->pattern([], 'fr'));
    }
}
