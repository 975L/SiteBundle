<?php
namespace c975L\SiteBundle\Command;

use Twig\Environment;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;

/**
 * Console command to create sitemap of pages, executed with 'pageedit:createSitemap'
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2017 975L <contact@975l.com>
 */
#[AsCommand(
    name: 'site:sitemaps:create',
    description: 'Creates the sitemap of pages located in templates/pages folder'
)]
class SitemapCreateCommand extends Command
{
    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly Environment $environment

    )
    {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->createSitemap();

        $io->success('Sitemaps created.');

        return Command::SUCCESS;
    }

    private function createSitemap(): void
    {
        $root = $this->configService->getContainerParameter('kernel.project_dir');
        $urlRoot = $this->configService->getParameter('c975LCommon.url');
        $folderPath = $root . '/templates/pages';

        //Gets pages
        $finder = new Finder();
        $finder
            ->files()
            ->in($folderPath)
            ->depth('== 0')
            ->name('*.html.twig')
            ->sortByName()
            ;

        // Urls for the pages
        $urls = [];
        foreach ($finder as $file) {
            $fileContent = $file->getContents();
            $url = $urlRoot . '/pages/' . str_replace('.html.twig', '', $file->getRelativePathname());
            $url = $url === $urlRoot . "/pages/home" ? $urlRoot : $url;
            $urls[]= [
                'loc' => $url,
                'lastmod' => date('Y-m-d', $file->getMTime()),
                'changefreq' => $this->getChangeFrequency($fileContent),
                'priority' => $this->getPriority($fileContent),
            ];
        }

        //Writes file
        $sitemapContent = $this->environment->render('@c975LSite/sitemap.xml.twig', ['urls' => $urls]);
        $sitemapFile = $root . '/public/sitemap-pages.xml';
        file_put_contents($sitemapFile, $sitemapContent);
    }

    // Returns frequency from file content
    public function getChangeFrequency(string $fileContent)
    {
        $changeFrequency = 'monthly';

        preg_match('/changeFrequency=\"(.*)\"/', $fileContent, $matches);
        if (!empty($matches)) {
            $changeFrequency = $matches[1];
        }

        return $changeFrequency;
    }

    // Returns priority from file content
    public function getPriority(string $fileContent)
    {
        $priority = 5;

        preg_match('/priority=\"(.*)\"/', $fileContent, $matches);
        if (!empty($matches)) {
            $priority = (int) $matches[1];
        }

        return $priority;
    }
}
