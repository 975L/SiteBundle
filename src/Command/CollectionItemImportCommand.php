<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Command;

use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Management\CollectionGroupResolver;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use c975L\UiBundle\Service\UniqueSlug;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\String\Slugger\SluggerInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// One-off migration helper: imports a legacy JSON array of items (e.g. 975l.com's hand-maintained projects.json) into CollectionItem rows, so an app that used to hand-roll its own JSON-driven list can switch to the "collection" block + this CRUD instead. Expected JSON shape: a plain array of objects, each with "title" (required), and optionally "description", "url", "image" (a path to an existing image file, resolved against --images-dir).
#[AsCommand(
    name: 'c975l:site:collection-item:import',
    description: 'Import a legacy JSON array of items into CollectionItem rows for a given group'
)]
class CollectionItemImportCommand extends Command
{
    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CollectionItemRepository $collectionItemRepository,
        private readonly CollectionGroupResolver $collectionGroupResolver,
        private readonly SluggerInterface $slugger,
        #[Autowire(param: 'kernel.project_dir')]
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

        $groupName = $input->getOption('group');
        $jsonFile = $this->projectDir . '/' . ltrim($input->getOption('json-file'), '/');
        $imagesDir = $this->projectDir . '/' . ltrim($input->getOption('images-dir'), '/');
        $dryRun = $input->getOption('dry-run');

        if (!$groupName) {
            $io->error('Option --group is required.');

            return Command::FAILURE;
        }

        $rows = $this->readRows($io, $jsonFile);
        if (null === $rows) {
            return Command::FAILURE;
        }

        $io->title(sprintf('Importing into collection "%s"', $groupName));
        $io->text($dryRun
            ? '<comment>DRY-RUN — nothing will be persisted</comment>'
            : '<info>LIVE — changes will be flushed</info>');
        $io->newLine();

        [$collectionGroup, $state] = $this->resolveTarget($groupName, $dryRun);
        [$created, $skipped] = $this->importRows($rows, $collectionGroup, $state, $io, $imagesDir, $dryRun);

        if (!$dryRun) {
            $this->em->flush();
        }

        $io->newLine();
        $io->success(sprintf(
            '%d items %s. %d skipped.',
            $created,
            $dryRun ? 'would be created' : 'created and flushed',
            $skipped
        ));

        return Command::SUCCESS;
    }

    // The file's rows, or null once the reason it couldn't be read has been reported
    private function readRows(SymfonyStyle $io, string $jsonFile): ?array
    {
        if (!is_file($jsonFile)) {
            $io->error("File not found: {$jsonFile}");

            return null;
        }

        $rows = json_decode(file_get_contents($jsonFile), true);
        if (!is_array($rows)) {
            $io->error("File '{$jsonFile}' does not contain a valid JSON array.");

            return null;
        }

        return $rows;
    }

    // Resolved by normalized slug, so re-running with different casing hits the same collection instead of duplicating it
    // @return array{0: CollectionGroup, 1: array} - the collection, and what the row loop starts from
    private function resolveTarget(string $groupName, bool $dryRun): array
    {
        $groupUsedSlugs = [];
        [$collectionGroup, $isNew] = $this->collectionGroupResolver->resolve($groupName, $groupUsedSlugs);
        if ($isNew && !$dryRun) {
            $this->em->persist($collectionGroup);
        }

        $existingItems = $isNew ? [] : $this->collectionItemRepository->findByCollectionGroup($collectionGroup);

        return [$collectionGroup, [
            'titles' => array_map(static fn (CollectionItem $item): string => $item->getTitle(), $existingItems),
            // Tracks slugs assigned so far in this run too, since two different titles imported in the same batch could still normalize to the same slug before either is flushed to the DB
            'slugs' => array_map(static fn (CollectionItem $item): string => (string) $item->getSlug(), $existingItems),
            'position' => $isNew ? 0 : $this->collectionItemRepository->countByCollectionGroup($collectionGroup),
        ]];
    }

    // @return array{0: int, 1: int} - created, skipped
    private function importRows(array $rows, CollectionGroup $collectionGroup, array $state, SymfonyStyle $io, string $imagesDir, bool $dryRun): array
    {
        $created = 0;
        $skipped = 0;

        foreach ($rows as $row) {
            $item = $this->buildItem($row, $collectionGroup, $state, $io, $imagesDir);
            if (null === $item) {
                ++$skipped;
                continue;
            }

            $io->writeln(sprintf('  <info>[+]</info> %s%s', $item->getTitle(), $dryRun ? ' <comment>(dry-run)</comment>' : ''));
            if (!$dryRun) {
                $this->em->persist($item);
            }
            ++$created;
        }

        return [$created, $skipped];
    }

    // One JSON row as an item, or null when there's nothing to import from it (no title, or a title the collection already holds) - $state carries the titles/slugs already taken and where the next item goes, updated as rows go through
    private function buildItem(array $row, CollectionGroup $collectionGroup, array &$state, SymfonyStyle $io, string $imagesDir): ?CollectionItem
    {
        $title = $row['title'] ?? null;
        if (!$title) {
            $io->warning('Row with no "title" - skipped.');

            return null;
        }

        if (in_array($title, $state['titles'], true)) {
            $io->writeln("  <comment>[skip]</comment> {$title} (already imported)");

            return null;
        }

        $slug = UniqueSlug::build(
            $this->slugger,
            $title,
            static fn (string $candidate): bool => in_array($candidate, $state['slugs'], true)
        );
        $state['slugs'][] = $slug;

        $item = new CollectionItem()
            ->setCollectionGroup($collectionGroup)
            ->setTitle($title)
            ->setSlug($slug)
            ->setDescription($row['description'] ?? null)
            ->setUrl($row['url'] ?? null)
            ->setPosition($state['position']++)
        ;

        $this->attachImage($item, $row['image'] ?? null, $imagesDir, $io);

        return $item;
    }

    // A missing image only warns - the item itself is still worth importing, its picture can be added from the admin afterwards
    private function attachImage(CollectionItem $item, ?string $image, string $imagesDir, SymfonyStyle $io): void
    {
        if (null === $image) {
            return;
        }

        $path = $imagesDir . '/' . ltrim($image, '/');
        if (!is_file($path)) {
            $io->warning(sprintf('  Image not found for "%s": %s', $item->getTitle(), $path));

            return;
        }

        $item->setFile(new ReplacingFile($path));
    }
}
