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

    private function tester(array $pageUrls, array $assets, array $checkReturns): CommandTester
    {
        $smokeTestClient = $this->createStub(SmokeTestClient::class);
        $smokeTestClient->method('findAssets')->willReturn($assets);
        $smokeTestClient->method('check')->willReturnOnConsecutiveCalls(...$checkReturns);

        return new CommandTester($this->command($pageUrls, $smokeTestClient));
    }

    // One published Page per given url, the resolver stub handing them back in that same order (the command resolves each page exactly once, in findAllOrdered()'s order)
    private function command(array $pageUrls, SmokeTestClient $smokeTestClient, ?ConfigServiceInterface $configService = null): SmokeTestCommand
    {
        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findAllOrdered')->willReturn(array_map(static fn (): Page => new Page(), $pageUrls));

        $pagePublicUrlResolver = $this->createStub(PagePublicUrlResolver::class);
        if ($pageUrls) {
            $pagePublicUrlResolver->method('resolve')->willReturnOnConsecutiveCalls(...$pageUrls);
        }

        if (null === $configService) {
            $configService = $this->createStub(ConfigServiceInterface::class);
            $configService->method('get')->willReturn('https://example.com');
        }

        return new SmokeTestCommand($pageRepository, $pagePublicUrlResolver, $smokeTestClient, $configService);
    }
}
