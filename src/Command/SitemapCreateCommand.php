<?php
/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Command;

use c975L\SiteBundle\Service\PageServiceInterface;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Twig\Environment;

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
    private $urlRoot;
    private $sitemapFolder;

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly Environment $environment,
        private readonly ConfigServiceInterface $configService,
        private readonly PageServiceInterface $pageService,
    ) {
        parent::__construct();
        $this->sitemapFolder = $this->parameterBag->get('kernel.project_dir') . '/public';
        $this->urlRoot = $this->configService->get('url');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $this->createSitemap();

        $io->success('Sitemaps created.');

        return Command::SUCCESS;
    }

    // Creates the sitemap
    private function createSitemap(): void
    {
        $urlsPages = $this->getUrlsFromFolder();
        $urlsDatabase = $this->getUrlsFromDatabase();
        $urls = array_merge($urlsPages, $urlsDatabase);

        //Writes file
        $sitemapContent = $this->environment->render('@c975LSite/sitemap.xml.twig', ['urls' => $urls]);
        $sitemapFile = $this->sitemapFolder . '/sitemap-pages.xml';
        file_put_contents($sitemapFile, $sitemapContent);
    }


    // Gets urls form physical files in templates/pages folder
    public function getUrlsFromFolder(): array
    {
        $finder = new Finder();
        $finder
            ->files()
            ->in($this->parameterBag->get('kernel.project_dir') . '/templates/pages')
            ->depth('== 0')
            ->name('*.html.twig')
            ->sortByName()
        ;

        // Urls for the pages
        $urls = [];
        foreach ($finder as $file) {
            $fileContent = $file->getContents();
            $url = $this->urlRoot . '/pages/' . str_replace('.html.twig', '', $file->getRelativePathname());
            $url = $url === $this->urlRoot . "/pages/home" ? $this->urlRoot : $url;
            $urls[]= [
                'loc' => $url,
                'lastmod' => date('Y-m-d', $file->getMTime()),
                'changefreq' => $this->getChangeFrequency($fileContent),
                'priority' => $this->getPriority($fileContent),
            ];
        }

        return $urls;
    }

    // Gets urls from database
    public function getUrlsFromDatabase(): array
    {
        $pages = $this->pageService->findAll();

        // Urls for the pages
        $urls = [];
        foreach ($pages as $page) {
            $url = $this->urlRoot . '/pages/' . $page->getSlug();
            $urls[]= [
                'loc' => $url,
                'lastmod' => date('Y-m-d', $page->getModification()->getTimestamp()),
                'changefreq' => 'weekly',
                'priority' => 9,
            ];
        }

        return $urls;
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
