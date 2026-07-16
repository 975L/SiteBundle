<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Command;

use c975L\SiteBundle\Entity\CollectionEntry;
use c975L\SiteBundle\Repository\CollectionEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// One-off migration helper: imports a legacy JSON array of items (e.g. 975l.com's hand-maintained
// projects.json) into CollectionEntry rows, so an app that used to hand-roll its own JSON-driven list
// can switch to the "collection" block + this CRUD instead. Expected JSON shape: a plain array of
// objects, each with "title" (required), and optionally "description", "url", "image" (a path to an
// existing image file, resolved against --images-dir).
#[AsCommand(
    name: 'c975l:site:collection-entry:import',
    description: 'Import a legacy JSON array of items into CollectionEntry rows for a given group'
)]
class CollectionEntryImportCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CollectionEntryRepository $collectionEntryRepository,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('group', null, InputOption::VALUE_REQUIRED, 'Group these entries belong to (e.g. "projects")')
            ->addOption('json-file', null, InputOption::VALUE_REQUIRED, 'Path to the JSON file, relative to the project dir')
            ->addOption('images-dir', null, InputOption::VALUE_OPTIONAL, 'Directory the JSON\'s "image" paths are relative to, relative to the project dir', '.')
            ->addOption('dry-run', null, InputOption::VALUE_NONE, 'Simulate without persisting anything')
        ;
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $group = $input->getOption('group');
        $jsonFile = $this->projectDir . '/' . ltrim($input->getOption('json-file'), '/');
        $imagesDir = $this->projectDir . '/' . ltrim($input->getOption('images-dir'), '/');
        $dryRun = $input->getOption('dry-run');

        if (!$group) {
            $io->error('Option --group is required.');

            return Command::FAILURE;
        }

        if (!is_file($jsonFile)) {
            $io->error("File not found: {$jsonFile}");

            return Command::FAILURE;
        }

        $rows = json_decode(file_get_contents($jsonFile), true);
        if (!is_array($rows)) {
            $io->error("File '{$jsonFile}' does not contain a valid JSON array.");

            return Command::FAILURE;
        }

        $io->title(sprintf('Importing into collection group "%s"', $group));
        $io->text($dryRun
            ? '<comment>DRY-RUN — nothing will be persisted</comment>'
            : '<info>LIVE — changes will be flushed</info>');
        $io->newLine();

        $existingTitles = array_map(
            static fn (CollectionEntry $entry): string => $entry->getTitle(),
            $this->collectionEntryRepository->findByGroup($group)
        );
        $position = $this->collectionEntryRepository->countByGroup($group);
        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $title = $row['title'] ?? null;

            if (!$title) {
                $io->warning('Row with no "title" - skipped.');
                ++$skipped;
                continue;
            }

            if (in_array($title, $existingTitles, true)) {
                $io->writeln("  <comment>[skip]</comment> {$title} (already imported)");
                ++$skipped;
                continue;
            }

            $entry = (new CollectionEntry())
                ->setGroup($group)
                ->setTitle($title)
                ->setDescription($row['description'] ?? null)
                ->setUrl($row['url'] ?? null)
                ->setPosition($position)
            ;

            $imagePath = null !== ($row['image'] ?? null) ? $imagesDir . '/' . ltrim($row['image'], '/') : null;
            if (null !== $imagePath && is_file($imagePath)) {
                $entry->setFile(new ReplacingFile($imagePath));
            } elseif (null !== $imagePath) {
                $io->warning("  Image not found for \"{$title}\": {$imagePath}");
            }

            $io->writeln(sprintf('  <info>[+]</info> %s%s', $title, $dryRun ? ' <comment>(dry-run)</comment>' : ''));

            if (!$dryRun) {
                $this->em->persist($entry);
            }

            ++$position;
            ++$created;
        }

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->newLine();
        $io->success(sprintf(
            '%d entries %s. %d skipped.',
            $created,
            $dryRun ? 'would be created' : 'created and flushed',
            $skipped
        ));

        return Command::SUCCESS;
    }
}
