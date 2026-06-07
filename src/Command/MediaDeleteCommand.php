<?php

/*
 * (c) 2025: 975L <contact@975l.com>
 * (c) 2025: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Command;

use Symfony\Component\Finder\Finder;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Console\Attribute\AsCommand;
use c975L\SiteBundle\Repository\ArticleMediaRepository;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

#[AsCommand(
    name: 'site:media:delete',
    description: 'Deletes unused medias from c975L/SiteBundle and updates database for inexisting ones',
)]
class MediaDeleteCommand extends Command
{
    private string $publicDir;
    private Filesystem $filesystem;

    public function __construct(
        private readonly ArticleMediaRepository $articleMediaRepository,
        private readonly ParameterBagInterface $parameterBag,
    ) {
        parent::__construct();
        $this->publicDir = $this->parameterBag->get('kernel.project_dir') . '/public/';
        $this->filesystem = new Filesystem();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $mediasDb = $this->collectMediasFromDatabase();
        $mediasFiles = $this->collectMediasFromFilesystem();

        $this->removeUnusedFiles($mediasFiles, $mediasDb, $io);
        $this->updateDatabaseForMissingFiles($mediasDb, $mediasFiles, $io);

        $io->success('Unused medias deleted.');

        return Command::SUCCESS;
    }

    // Gets the list of media files from the database
    private function collectMediasFromDatabase(): array
    {
        $mediasDb = [];

        // Adds the media files of products
        $medias = $this->articleMediaRepository->findAll();
        foreach($medias as $media) {
            if (null !== $media->getName()) {
                $mediasDb[] = $this->publicDir . $media->getName();
            }
        }

        return $mediasDb;
    }

    // Gets the list of media files from the filesystem
    private function collectMediasFromFilesystem(): array
    {
        $mediasFiles = [];

        $finder = new Finder();
        $finder
            ->files()
            ->in($this->publicDir . 'medias/site/')
            ->depth('1');

        if ($finder->hasResults()) {
            foreach ($finder as $file) {
                $mediasFiles[] = $file->getRealPath();
            }
        }

        return $mediasFiles;
    }

    // Suppress the files that are not referenced in the database
    private function removeUnusedFiles(array $mediasFiles, array $mediasDb, SymfonyStyle $io): void
    {
        $unusedFiles = array_diff($mediasFiles, $mediasDb);

        if (count($unusedFiles) > 0) {
            $io->title('Unused medias:');
            $io->listing($unusedFiles);

            foreach ($unusedFiles as $file) {
                $this->filesystem->remove($file);
            }
        }
    }

    // Updates the database for files that are not referenced in the filesystem
    private function updateDatabaseForMissingFiles(array $mediasDb, array $mediasFiles, SymfonyStyle $io): void
    {
        $inexistingFiles = array_diff($mediasDb, $mediasFiles);

        if (count($inexistingFiles) > 0) {
            // Checks if the file exists in the public directory
            foreach ($inexistingFiles as $key => $file) {
                // Check if the file exists in the public directory
                if (strpos($file, '/public/') !== false) {
                    if (file_exists($file)) {
                        unset($inexistingFiles[$key]);
                    }
                }
            }

            $inexistingFiles = array_unique($inexistingFiles);
        }

        // Process the inexisting files
        if (count($inexistingFiles) > 0) {
            $io->title('Inexisting files:');
            $io->listing($inexistingFiles);

            // Updates database
            foreach ($inexistingFiles as $file) {
                $this->articleMediaRepository->updateDatabaseByName($file);
            }
        }
    }
}