<?php

namespace App\Command;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\ArrayInput;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\NullOutput;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\HttpKernel\KernelInterface;
use Twig\Environment;

#[AsCommand(
    name: 'app:sitemaps:create',
    description: 'Creates the sitemaps for the site and bundle\'s related sitemaps',
)]
class SitemapCreateCommand extends Command
{
    private $sitemaps = [];

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly Environment $environment,
        private readonly KernelInterface $kernel
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->createSitemap();

        $output->writeln('Sitemaps created!');

        return Command::SUCCESS;
    }

    // Creates the sitemap
    public function createSitemap(): void
    {
        $this->createSubSitemaps();
        $this->createSitemapIndex();
    }

    // Defines and creates all the sub-sitemaps
    public function createSubSitemaps(): void
    {
        $application = $this->getApplication() ?? new Application($this->kernel);
        $application->setAutoExit(false);

        $commands = [
            // 'book' => 'c975l:book:sitemaps:create',
            // 'shop' => 'c975l:shop:sitemaps:create',
            'site' => 'c975l:site:sitemaps:create',
        ];

        foreach ($commands as $name => $commandName) {
            $this->sitemaps[] = $this->configService->get('site-url') . '/sitemap-' . $name . '.xml';
            $command = $application->find($commandName);
            $inputArray = new ArrayInput([]);
            $command->run($inputArray, new NullOutput());
        }

        $this->createSitemapSite();
    }

    // Creates the sitemap for pages specific to site
    public function createSitemapSite(): void
    {
        //Defines main pages
        $urls = [];
        $urlsList = [
            'contact' => 'monthly, 0.4'
        ];
        foreach ($urlsList as $key => $value) {
            $values = explode(',', $value);
            $urls[] = [
                'loc' => $this->configService->get('site-url') . '/' . $key,
                'lastmod' => null,
                'changefreq' => trim($values[0]),
                'priority' => trim($values[1])
            ];
        }

        $this->sitemaps[] = $this->configService->get('site-url') . '/sitemap-pages.xml';

        //Writes file
        $sitemapContent = $this->environment->render('@c975LSite/sitemap.xml.twig', ['urls' => $urls]);
        $sitemapFile = $this->configService->getContainerParameter('kernel.project_dir') . '/public/sitemap-pages.xml';
        file_put_contents($sitemapFile, $sitemapContent);
    }

    // Creates sitemap index
    public function createSitemapIndex(): void
    {
        $sitemapIndexContent = $this->environment->render('@c975LSite/sitemap-index.xml.twig', ['sitemaps' => $this->sitemaps]);
        $sitemapIndexFile = $this->configService->getContainerParameter('kernel.project_dir') . '/public/sitemap-index.xml';
        file_put_contents($sitemapIndexFile, $sitemapIndexContent);
    }
}
