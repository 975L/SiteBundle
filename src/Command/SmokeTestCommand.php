<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Command;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\SmokeTestClient;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * Checks that every published page, and every css/js asset the home page references, answers 200.
 * Meant to run at the end of a deployment: it returns a non-zero exit code on the first non-200,
 * so a CI job fails immediately instead of leaving a broken site online unnoticed.
 *
 * Deliberately NOT a HealthCheckProviderInterface implementation: the health check judges a live
 * site's quality on a weekly schedule and persists rows for a dashboard, this answers "is it
 * broken, right now" and must be able to fail a pipeline.
 *
 * Usage:
 *   php bin/console c975l:site:smoke-test
 *   php bin/console c975l:site:smoke-test --pages-only
 *
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
#[AsCommand(
    name: 'c975l:site:smoke-test',
    description: 'Checks that every published page and every css/js asset it references answers 200 - meant to run right after a deployment'
)]
class SmokeTestCommand extends Command
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PagePublicUrlResolver $pagePublicUrlResolver,
        private readonly SmokeTestClient $smokeTestClient,
        private readonly ConfigServiceInterface $configService,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('pages-only', null, InputOption::VALUE_NONE, 'Only checks the pages, skipping their css/js assets');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $siteUrl = (string) $this->configService->get('site-url');
        if ('' === $siteUrl) {
            $io->error('La configuration "site-url" n\'est pas définie, impossible de savoir quelles urls tester.');

            return Command::FAILURE;
        }

        // A site left in maintenance answers 503 + the maintenance page (inline css only) on every public url, so every check below would fail on a deployment that actually went fine - the site being in maintenance is a deliberate state, not a broken deployment
        if (true === $this->configService->get('site-maintenance')) {
            $io->warning('Le site est en mode maintenance, smoke test ignoré (les pages publiques répondent 503 par construction).');

            return Command::SUCCESS;
        }

        // The very same set of pages the health check and the sitemap use (published, not deleted), so a site has one single notion of "page that must answer 200" rather than three lists drifting apart
        $pageUrls = [];
        foreach ($this->pageRepository->findAllOrdered() as $page) {
            $url = $this->pagePublicUrlResolver->resolve($page);
            if (null !== $url) {
                $pageUrls[] = $url;
            }
        }

        if (!$pageUrls) {
            $io->warning('Aucune page publiée à tester.');

            return Command::SUCCESS;
        }

        $statuses = $this->smokeTestClient->check($pageUrls);

        // Assets are read from the home page only: it already references the whole shared front-end (the app entrypoint plus every bundle's preloaded controllers and the compiled stylesheet), which is what a deployment can break - a page-specific asset adds pages x assets requests for very little more coverage
        if (!$input->getOption('pages-only')) {
            $assets = $this->smokeTestClient->findAssets($siteUrl, $siteUrl);
            if (!$assets) {
                // The pages are listed first: a home page without a single asset is almost always a home page that isn't rendering at all (500, redirect elsewhere), and their status codes say so - "aucun asset trouvé" alone sends someone looking at asset-map:compile while the site is down for another reason
                $this->listFailures($io, $statuses);
                $io->error('Aucun asset css/js trouvé dans le HTML de la page d\'accueil.');

                return Command::FAILURE;
            }

            $statuses = [...$statuses, ...$this->smokeTestClient->check($assets)];
        }

        return $this->report($io, $output, $statuses);
    }

    // Only failures are listed by default: a deployment log with 50 "ok" lines in it is a log nobody reads. -v prints every url checked
    private function report(SymfonyStyle $io, OutputInterface $output, array $statuses): int
    {
        $failures = array_filter($statuses, static fn (int $status): bool => 200 !== $status);

        if ($output->isVerbose()) {
            foreach ($statuses as $url => $status) {
                $io->writeln(sprintf('  %s %3d  %s', 200 === $status ? 'ok  ' : 'FAIL', $status, $url));
            }
        }

        if (!$failures) {
            $io->success(sprintf('%d url(s) vérifiée(s), toutes en 200.', \count($statuses)));

            return Command::SUCCESS;
        }

        $this->listFailures($io, $statuses);

        $io->error(sprintf('%d url(s) en échec sur %d vérifiée(s).', \count($failures), \count($statuses)));

        return Command::FAILURE;
    }

    // Every non-200 url, one per line
    private function listFailures(SymfonyStyle $io, array $statuses): void
    {
        foreach ($statuses as $url => $status) {
            if (200 === $status) {
                continue;
            }

            // 0 is this command's own marker for "no answer at all", not an HTTP status - showing it as such would send someone looking for a status code that doesn't exist
            $io->writeln(sprintf('  <error>FAIL</error> %s  %s', 0 === $status ? 'pas de réponse' : (string) $status, $url));
        }
    }
}
