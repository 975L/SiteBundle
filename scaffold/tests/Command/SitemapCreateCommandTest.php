<?php

namespace App\Tests\Command;

use App\Command\SitemapCreateCommand;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;

class SitemapCreateCommandTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/sitemap-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0777, true);
    }

    protected function tearDown(): void
    {
        array_map('unlink', glob($this->projectDir . '/public/*') ?: []);
        @rmdir($this->projectDir . '/public');
        @rmdir($this->projectDir);
    }

    public function testCreateSitemapSiteWritesPagesSitemapFile(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnMap([
            ['site-url', 'https://example.test'],
        ]);
        $configService->method('getContainerParameter')->willReturnMap([
            ['kernel.project_dir', $this->projectDir],
        ]);

        $environment = $this->createMock(Environment::class);
        $environment->expects($this->once())
            ->method('render')
            ->with('@c975LSite/sitemap.xml.twig', $this->callback(function (array $context) {
                return $context['urls'][0]['loc'] === 'https://example.test/contact'
                    && $context['urls'][0]['changefreq'] === 'monthly'
                    && $context['urls'][0]['priority'] === '0.4';
            }))
            ->willReturn('<urlset><url><loc>https://example.test/contact</loc></url></urlset>');

        $command = new SitemapCreateCommand($configService, $environment, $this->createStub(KernelInterface::class));
        $command->createSitemapSite();

        $this->assertFileExists($this->projectDir . '/public/sitemap-pages.xml');
        $this->assertSame(
            '<urlset><url><loc>https://example.test/contact</loc></url></urlset>',
            file_get_contents($this->projectDir . '/public/sitemap-pages.xml')
        );
    }

    public function testCreateSitemapIndexWritesIndexFile(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('getContainerParameter')->willReturnMap([
            ['kernel.project_dir', $this->projectDir],
        ]);

        $environment = $this->createMock(Environment::class);
        $environment->expects($this->once())
            ->method('render')
            ->with('@c975LSite/sitemap-index.xml.twig', ['sitemaps' => []])
            ->willReturn('<sitemapindex></sitemapindex>');

        $command = new SitemapCreateCommand($configService, $environment, $this->createStub(KernelInterface::class));
        $command->createSitemapIndex();

        $this->assertFileExists($this->projectDir . '/public/sitemap-index.xml');
        $this->assertSame('<sitemapindex></sitemapindex>', file_get_contents($this->projectDir . '/public/sitemap-index.xml'));
    }
}
