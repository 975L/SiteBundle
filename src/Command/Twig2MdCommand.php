<?php
/*
 * (c) 2018: 975L <contact@975l.com>
 * (c) 2018: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * Fork from: https://github.com/BastienL/Symfony2Loc
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Command;

use Symfony\Component\Finder\Finder;
use Symfony\Component\HttpKernel\Kernel;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Console command to convert the Twig models template to their Markdown format with 'models:twig2md'
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2018 975L <contact@975l.com>
 */
#[AsCommand(
    name: 'models:twig2md',
    description: 'Convert c975L/SiteBundle twig models templates to markdown format'
)]
class Twig2MdCommand extends Command
{
    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        //Defines console output
        $io = new SymfonyStyle($input, $output);
        $io->title('Twig2Md conversion');

        //Gets the Finder
        $finder = new Finder();

        //Defines paths
        $folders = ['/france/fr'];

        //Gets files
        foreach ($folders as $folder) {
            $finder
                ->files()
                ->in(__DIR__ . '/../Resources/views/models' . $folder)
                ->name('*.html.twig')
                ->sortByType()
                ;
        }

        //Converts files to markdown
        foreach ($finder as $file) {
            //Ouputs filename
            $io->text(' Converting --> ' . $file->getFilename());

            //Gets content
            $markdown = file_get_contents($file->getPathname());

            //Deletes unsused tags
            $suppress = ['</h1>', '</h2>', '</h3>', '</h4>', '</h5>', '</h6>', '</p>', '<ul>', '</ul>', '<ol>', '</ol>', '</li>', '{% endblock %}', "{{ 'label.latest_update'|trans }} : {{ max(updateDate, latestUpdate)|format_datetime('long', 'none', '') }}", "{{ 'text.only_french'|trans }}"];
            $markdown = str_replace($suppress, '', $markdown);

            //Replaces formatting
            $markdown = str_replace(['<strong>', '</strong>'], '**', $markdown);
            $markdown = str_replace('<li>', '- ', $markdown);
            $markdown = str_replace('<h1>', '# ', $markdown);
            $markdown = str_replace('<h2>', '## ', $markdown);
            $markdown = str_replace('<h3>', '### ', $markdown);
            $markdown = str_replace('<h4>', '#### ', $markdown);
            $markdown = str_replace('<h5>', '##### ', $markdown);
            $markdown = str_replace('<h6>', '###### ', $markdown);
            $markdown = str_replace('<br>', "\n\n", $markdown);

            //Deletes spaces
            $markdown = str_replace('    ', '', $markdown);
            $markdown = str_replace(['  ', '  ', '  ', '  '], ' ', $markdown);

            //Deletes blocks
            $markdown = preg_replace('/{%(.*)%}/', '', $markdown);
            $markdown = preg_replace('/{#(.*)#}/', '', $markdown);
            $markdown = preg_replace('/{#(.*)#}/s', '', $markdown);
            $markdown = preg_replace('/<p(.*)>/', '', $markdown);

            //Replaces line feed
            $markdown = str_replace(" \n", "\n", $markdown);
            $markdown = preg_replace("/(\n){3,}/", "\n\n", $markdown);
            $markdown = preg_replace("/[\r\n]{2,}/", "\n\n", $markdown);

            //Writes file
            $markdownFilename = str_replace('.html.twig', '.md', $file->getPathname());
            $contentMarkdown = null;
            if (is_file($markdownFilename)) {
                $contentMarkdown = file_get_contents($markdownFilename);
            }
            if ($contentMarkdown === null || $contentMarkdown != $markdown) {
                file_put_contents($markdownFilename, $markdown);
            }
        }

        //Output data
        $io->success('All Twig files converted!');

        if (str_starts_with(Kernel::VERSION, '5')) {
            return Command::SUCCESS;
        }

        return Command::SUCCESS;
    }
}
