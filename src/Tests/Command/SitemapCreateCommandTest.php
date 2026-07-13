<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Command;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Command\SitemapCreateCommand;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Service\PageServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Twig\Environment;

// Lives under src/Tests (not a sibling tests/ dir) so it stays autoloadable by consuming apps,
// whose attribute route loader recursively reflects every class under the bundle root
class SitemapCreateCommandTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/c975l-sitemap-command-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    // Recursively deletes a directory tree (no external dependency needed for this test-only cleanup)
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    private function createParameterBag(): ParameterBagInterface
    {
        $bag = $this->createStub(ParameterBagInterface::class);
        $bag->method('get')->willReturnCallback(
            fn (string $name): string => 'kernel.project_dir' === $name ? $this->projectDir : ''
        );

        return $bag;
    }

    private function createConfigService(string $urlRoot = 'https://example.com'): ConfigServiceInterface
    {
        $service = $this->createStub(ConfigServiceInterface::class);
        $service->method('get')->willReturn($urlRoot);

        return $service;
    }

    private function createPageService(array $pages): PageServiceInterface
    {
        $service = $this->createStub(PageServiceInterface::class);
        $service->method('findAll')->willReturn($pages);

        return $service;
    }

    private function createCommand(array $pages = [], string $urlRoot = 'https://example.com'): SitemapCreateCommand
    {
        return new SitemapCreateCommand(
            $this->createParameterBag(),
            $this->createStub(Environment::class),
            $this->createConfigService($urlRoot),
            $this->createPageService($pages)
        );
    }

    // Each page is turned into an absolute URL, using its own priority/changeFrequency
    public function testGetUrlsBuildsAbsoluteUrlsFromPageAttributes(): void
    {
        $page = (new Page())->setTitle('About')->setSlug('about')->setPriority(7)->setChangeFrequency('daily');
        $page->setModification(new \DateTime('2026-01-15'));
        $command = $this->createCommand([$page], 'https://example.com');

        $urls = $command->getUrls();

        $this->assertSame([
            'loc' => 'https://example.com/pages/about',
            'lastmod' => '2026-01-15',
            'changefreq' => 'daily',
            'priority' => 7,
        ], $urls[0]);
    }

    // A page with no explicit priority/changeFrequency falls back to sensible sitemap defaults
    public function testGetUrlsFallsBackToDefaultPriorityAndChangeFrequency(): void
    {
        $page = (new Page())->setTitle('Home')->setSlug('home');
        $page->setModification(new \DateTime('2026-01-15'));
        $command = $this->createCommand([$page]);

        $urls = $command->getUrls();

        $this->assertSame('weekly', $urls[0]['changefreq']);
        $this->assertSame(4, $urls[0]['priority']);
    }

    public function testGetUrlsReturnsEmptyArrayWhenNoPages(): void
    {
        $command = $this->createCommand([]);

        $this->assertSame([], $command->getUrls());
    }

    // Extracts the changeFrequency="..." attribute from a template's file content
    public function testGetChangeFrequencyExtractsValueFromFileContent(): void
    {
        $command = $this->createCommand();

        $this->assertSame('daily', $command->getChangeFrequency('<!-- changeFrequency="daily" -->'));
    }

    public function testGetChangeFrequencyDefaultsToMonthlyWhenAttributeMissing(): void
    {
        $command = $this->createCommand();

        $this->assertSame('monthly', $command->getChangeFrequency('<!-- nothing here -->'));
    }

    // Extracts the priority="..." attribute from a template's file content, cast to int
    public function testGetPriorityExtractsValueFromFileContent(): void
    {
        $command = $this->createCommand();

        $this->assertSame(8, $command->getPriority('<!-- priority="8" -->'));
    }

    public function testGetPriorityDefaultsToFiveWhenAttributeMissing(): void
    {
        $command = $this->createCommand();

        $this->assertSame(5, $command->getPriority('<!-- nothing here -->'));
    }

    // createSitemap() writes the rendered template to public/sitemap-site.xml
    public function testCreateSitemapWritesRenderedTemplateToPublicFolder(): void
    {
        $environment = $this->createStub(Environment::class);
        $environment->method('render')->willReturn('<urlset></urlset>');
        $command = new SitemapCreateCommand(
            $this->createParameterBag(),
            $environment,
            $this->createConfigService(),
            $this->createPageService([])
        );

        $command->createSitemap();

        $sitemapFile = $this->projectDir . '/public/sitemap-site.xml';
        $this->assertFileExists($sitemapFile);
        $this->assertSame('<urlset></urlset>', file_get_contents($sitemapFile));
    }
}
