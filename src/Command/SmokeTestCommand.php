<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Command;

use c975L\ConfigBundle\Management\SitemapProviderInterface;
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
use Symfony\Component\DependencyInjection\Attribute\AutowireIterator;

// Checks every published page, one slice of the titled sitemap-provider urls and home css/js asset answers 200, exiting non-zero on the first failure so a deployment pipeline fails; deliberately not a health check
/**
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
#[AsCommand(
    name: 'c975l:site:smoke-test',
    description: 'Checks that every published page, a slice of the titled sitemap urls and the css/js assets answer 200 - meant to run right after a deployment'
)]
class SmokeTestCommand extends Command
{
    // Sitemap urls checked per run. Every one of them, every hour, had a site declaring each of its records (1800 urls on resistance-haute-savoie.fr) fire thousands of requests an hour from the host's own address, until the hosting's front proxy answered 429 to every site sharing it
    private const int SITEMAP_SLICE_SIZE = 100;

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PagePublicUrlResolver $pagePublicUrlResolver,
        private readonly SmokeTestClient $smokeTestClient,
        private readonly ConfigServiceInterface $configService,
        /** @var iterable<SitemapProviderInterface> */
        #[AutowireIterator('c975l.sitemap_provider')]
        private readonly iterable $sitemapProviders = [],
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this->addOption('pages-only', null, InputOption::VALUE_NONE, 'Only checks the pages, skipping their css/js assets');
        $this->addOption('slice', null, InputOption::VALUE_REQUIRED, 'Which slice of the sitemap urls to check, defaults to the one the current hour points at');
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
            // A page kept for members answers the login form to this anonymous run, which is what it should do
            if ($page->isMembersOnly()) {
                continue;
            }
            $url = $this->pagePublicUrlResolver->resolve($page);
            if (null !== $url) {
                $pageUrls[] = $url;
            }
        }

        $pageUrls = array_values(array_unique([...$pageUrls, ...$this->sitemapSlice($input, $io, $pageUrls)]));

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

    // The titled urls the sitemap providers declare, which are the app's own routes (/tutoriels) no Page row stands for: untitled ones are left out, a gallery declaring every photo would turn a smoke test into a crawl. Only one slice of SITEMAP_SLICE_SIZE is returned, picked by the hour since the epoch so that every slice comes round in turn - a site with 1800 urls has each of them checked once in 18 hours, a small one has them all checked every run
    private function sitemapSlice(InputInterface $input, SymfonyStyle $io, array $pageUrls): array
    {
        $sitemapUrls = [];
        foreach ($this->sitemapProviders as $provider) {
            foreach ($provider->getUrls() as $url) {
                if (isset($url['title'])) {
                    $sitemapUrls[] = $url['loc'];
                }
            }
        }

        // Pages are checked anyway, so they don't take a place in a slice
        $slices = array_chunk(array_values(array_diff(array_unique($sitemapUrls), $pageUrls)), self::SITEMAP_SLICE_SIZE);
        if (!$slices) {
            return [];
        }

        $slice = (int) ($input->getOption('slice') ?? intdiv(time(), 3600)) % \count($slices);
        if ($io->isVerbose()) {
            $io->writeln(sprintf('Urls du sitemap : tranche %d sur %d', $slice + 1, \count($slices)));
        }

        return $slices[$slice];
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
