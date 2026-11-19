<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Command;

use c975L\ConfigBundle\Management\SitemapProviderInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Command\SmokeTestCommand;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\SmokeTestClient;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

class SmokeTestCommandTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/c975l-smoke-test-' . bin2hex(random_bytes(4));
        mkdir($this->projectDir . '/var', 0o777, true);
    }

    protected function tearDown(): void
    {
        @unlink($this->projectDir . '/var/maintenance');
        rmdir($this->projectDir . '/var');
        rmdir($this->projectDir);
    }

    public function testItSucceedsWhenEveryPageAndAssetAnswers200(): void
    {
        $tester = $this->tester(
            ['https://example.com/', 'https://example.com/pages/contact'],
            ['https://example.com/assets/app.js'],
            [
                ['https://example.com/' => 200, 'https://example.com/pages/contact' => 200],
                ['https://example.com/assets/app.js' => 200],
            ]
        );

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('3 url(s) vérifiée(s)', $tester->getDisplay());
    }

    public function testItFailsAndNamesTheOffendingPage(): void
    {
        $tester = $this->tester(
            ['https://example.com/', 'https://example.com/pages/contact'],
            ['https://example.com/assets/app.js'],
            [
                ['https://example.com/' => 200, 'https://example.com/pages/contact' => 500],
                ['https://example.com/assets/app.js' => 200],
            ]
        );

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $display = $tester->getDisplay();
        $this->assertStringContainsString('https://example.com/pages/contact', $display);
        $this->assertStringContainsString('1 url(s) en échec sur 3', $display);
    }

    // The failure a deployment actually produces: the page renders but asset-map:compile or the stylesheet warmer didn't run, so its hashed asset 404s
    public function testItFailsOnAMissingAssetEvenWhenEveryPageAnswers200(): void
    {
        $tester = $this->tester(
            ['https://example.com/'],
            ['https://example.com/bundles/build/site.css?v=1'],
            [
                ['https://example.com/' => 200],
                ['https://example.com/bundles/build/site.css?v=1' => 404],
            ]
        );

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('site.css', $tester->getDisplay());
    }

    // 0 is the client's marker for "no answer at all" - it must not be printed as if it were an HTTP status
    public function testItLabelsAnUnreachableUrlRatherThanShowingStatusZero(): void
    {
        $tester = $this->tester(
            ['https://example.com/'],
            ['https://example.com/assets/app.js'],
            [
                ['https://example.com/' => 0],
                ['https://example.com/assets/app.js' => 200],
            ]
        );

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('pas de réponse', $tester->getDisplay());
    }

    public function testPagesOnlySkipsTheAssetCheckEntirely(): void
    {
        $smokeTestClient = $this->createMock(SmokeTestClient::class);
        $smokeTestClient->expects($this->once())->method('check')->willReturn(['https://example.com/' => 200]);
        $smokeTestClient->expects($this->never())->method('findAssets');

        $tester = new CommandTester($this->command(['https://example.com/'], $smokeTestClient));

        $this->assertSame(Command::SUCCESS, $tester->execute(['--pages-only' => true]));
    }

    // A home page that renders but references no stylesheet at all is a broken deployment, not a site without assets
    public function testItFailsWhenTheHomePageReferencesNoAsset(): void
    {
        $tester = $this->tester(['https://example.com/'], [], [['https://example.com/' => 200]]);

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('Aucun asset css/js', $tester->getDisplay());
    }

    // The pages are what explain an assetless home page, so their status codes must show up rather than only "aucun asset trouvé"
    public function testItListsThePageFailuresWhenTheHomePageReferencesNoAsset(): void
    {
        $tester = $this->tester(['https://example.com/'], [], [['https://example.com/' => 503]]);

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $display = $tester->getDisplay();
        $this->assertStringContainsString('503', $display);
        $this->assertStringContainsString('Aucun asset css/js', $display);
    }

    // A site in maintenance answers 503 + a maintenance page with inline css only, on every public url: that's a deliberate state, so a deployment must not be reported as broken
    public function testItSkipsEverythingWhenTheSiteIsInMaintenance(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            static fn (string $key): mixed => 'site-maintenance' === $key ? true : 'https://example.com'
        );

        $smokeTestClient = $this->createMock(SmokeTestClient::class);
        $smokeTestClient->expects($this->never())->method('check');
        $smokeTestClient->expects($this->never())->method('findAssets');

        $tester = new CommandTester($this->command(['https://example.com/'], $smokeTestClient, $configService));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('maintenance', $tester->getDisplay());
    }

    // The flag a deployment drops makes Apache answer 503 on every url before the application even boots
    public function testItSkipsEverythingWhileADeploymentIsRunning(): void
    {
        touch($this->projectDir . '/var/maintenance');

        $smokeTestClient = $this->createMock(SmokeTestClient::class);
        $smokeTestClient->expects($this->never())->method('check');

        $tester = new CommandTester($this->command(['https://example.com/'], $smokeTestClient));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('maintenance', $tester->getDisplay());
    }

    // A flag left over by a deployment killed before its trap ran is stale: the whole site answering 503 is reported
    public function testItReportsTheFailuresBehindAStaleDeploymentFlag(): void
    {
        touch($this->projectDir . '/var/maintenance', time() - 3600);

        $tester = $this->tester(
            ['https://example.com/'],
            ['https://example.com/assets/app.js'],
            [['https://example.com/' => 503], ['https://example.com/assets/app.js' => 503]]
        );

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringNotContainsString('maintenance', $tester->getDisplay());
    }

    // The hourly run caught by a deployment starting mid-way: every url left answers 503, which is the deployment, not a broken site
    public function testItIgnoresTheFailuresOfADeploymentStartedMidRun(): void
    {
        $tester = $this->tester(
            ['https://example.com/', 'https://example.com/pages/contact'],
            ['https://example.com/assets/app.js'],
            [
                ['https://example.com/' => 200, 'https://example.com/pages/contact' => 200],
                ['https://example.com/assets/app.js' => 503],
            ],
            fn () => touch($this->projectDir . '/var/maintenance')
        );

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('déploiement a démarré', $tester->getDisplay());
    }

    // Same deployment caught before the home page is read: it answers 503 and so no asset at all
    public function testItIgnoresTheMissingAssetsOfADeploymentStartedMidRun(): void
    {
        $tester = $this->tester(
            ['https://example.com/'],
            [],
            [['https://example.com/' => 503]],
            fn () => touch($this->projectDir . '/var/maintenance')
        );

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringNotContainsString('Aucun asset css/js', $tester->getDisplay());
    }

    public function testItFailsWhenSiteUrlIsNotConfigured(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn(null);

        $tester = new CommandTester($this->command([], $this->createStub(SmokeTestClient::class), $configService));

        $this->assertSame(Command::FAILURE, $tester->execute([]));
        $this->assertStringContainsString('site-url', $tester->getDisplay());
    }

    public function testItSucceedsWithoutAnyPublishedPage(): void
    {
        $tester = new CommandTester($this->command([], $this->createStub(SmokeTestClient::class)));

        $this->assertSame(Command::SUCCESS, $tester->execute([]));
        $this->assertStringContainsString('Aucune page publiée', $tester->getDisplay());
    }

    // Only failures show by default, so a green deployment log stays short - -v is what lists every url
    public function testVerboseListsEveryCheckedUrl(): void
    {
        $tester = $this->tester(
            ['https://example.com/'],
            ['https://example.com/assets/app.js'],
            [['https://example.com/' => 200], ['https://example.com/assets/app.js' => 200]]
        );

        $tester->execute([], ['verbosity' => OutputInterface::VERBOSITY_VERBOSE]);
        $display = $tester->getDisplay();
        $this->assertStringContainsString('https://example.com/', $display);
        $this->assertStringContainsString('https://example.com/assets/app.js', $display);
    }

    public function testItChecksTheTitledUrlsOfTheSitemapProvidersOnce(): void
    {
        $provider = $this->createStub(SitemapProviderInterface::class);
        $provider->method('getUrls')->willReturn([
            ['loc' => 'https://example.com/tutoriels', 'title' => 'Tutoriels'],
            ['loc' => 'https://example.com/', 'title' => 'Accueil'],
            ['loc' => 'https://example.com/medias/photo.webp'],
        ]);

        $smokeTestClient = $this->createMock(SmokeTestClient::class);
        $smokeTestClient->expects($this->once())
            ->method('check')
            ->with(['https://example.com/', 'https://example.com/tutoriels'])
            ->willReturn(['https://example.com/' => 200, 'https://example.com/tutoriels' => 200]);

        $tester = new CommandTester($this->command(['https://example.com/'], $smokeTestClient, sitemapProviders: [$provider]));

        $this->assertSame(Command::SUCCESS, $tester->execute(['--pages-only' => true]));
    }

    // 250 sitemap urls make three slices of 100: the pages are checked every run, the sitemap urls one slice at a time, the slice wrapping round past the last one
    public function testItChecksOneSliceOfTheSitemapUrlsPerRun(): void
    {
        $sitemapUrls = array_map(static fn (int $i): string => 'https://example.com/livres/' . $i, range(1, 250));
        $provider = $this->createStub(SitemapProviderInterface::class);
        $provider->method('getUrls')->willReturn(array_map(static fn (string $loc): array => ['loc' => $loc, 'title' => $loc], $sitemapUrls));

        foreach ([0 => [1, 100], 2 => [201, 250], 4 => [101, 200]] as $slice => [$first, $last]) {
            $checked = [];
            $smokeTestClient = $this->createStub(SmokeTestClient::class);
            $smokeTestClient->method('check')->willReturnCallback(static function (array $urls) use (&$checked): array {
                $checked = $urls;

                return array_fill_keys($urls, 200);
            });

            $tester = new CommandTester($this->command(['https://example.com/'], $smokeTestClient, sitemapProviders: [$provider]));

            $this->assertSame(Command::SUCCESS, $tester->execute(['--pages-only' => true, '--slice' => (string) $slice]));
            $this->assertSame(
                ['https://example.com/', ...\array_slice($sitemapUrls, $first - 1, $last - $first + 1)],
                $checked
            );
        }
    }

    // $duringCheck runs on every check() call, standing for what happens on the server while the requests are in flight
    private function tester(array $pageUrls, array $assets, array $checkReturns, ?\Closure $duringCheck = null): CommandTester
    {
        $smokeTestClient = $this->createStub(SmokeTestClient::class);
        $smokeTestClient->method('findAssets')->willReturn($assets);
        $smokeTestClient->method('check')->willReturnCallback(static function () use (&$checkReturns, $duringCheck): array {
            if (null !== $duringCheck) {
                $duringCheck();
            }

            return array_shift($checkReturns);
        });

        return new CommandTester($this->command($pageUrls, $smokeTestClient));
    }

    // One published Page per given url, the resolver stub handing them back in that same order (the command resolves each page exactly once, in findAllOrdered()'s order)
    private function command(array $pageUrls, SmokeTestClient $smokeTestClient, ?ConfigServiceInterface $configService = null, array $sitemapProviders = []): SmokeTestCommand
    {
        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findAllOrdered')->willReturn(array_map(static fn (): Page => new Page(), $pageUrls));

        $pagePublicUrlResolver = $this->createStub(PagePublicUrlResolver::class);
        if ($pageUrls) {
            $pagePublicUrlResolver->method('resolve')->willReturnOnConsecutiveCalls(...array_values($pageUrls));
        }

        if (null === $configService) {
            $configService = $this->createStub(ConfigServiceInterface::class);
            $configService->method('get')->willReturn('https://example.com');
        }

        return new SmokeTestCommand($pageRepository, $pagePublicUrlResolver, $smokeTestClient, $configService, $this->projectDir, $sitemapProviders);
    }
}
