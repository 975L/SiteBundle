<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Command;

use c975L\SiteBundle\Service\DefaultPagesImporter;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

#[AsCommand(
    name: 'c975l:site:pages:import-defaults',
    description: 'Creates default pages (home, legal notice, privacy policy, ...) if they do not already exist'
)]
class DefaultPagesImportCommand extends Command
{
    public function __construct(
        private readonly DefaultPagesImporter $defaultPagesImporter,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $result = $this->defaultPagesImporter->import();

        if ($result['created'] === 0) {
            $io->warning('All default pages already exist, nothing was created.');

            return Command::SUCCESS;
        }

        $io->success(sprintf(
            '%d page(s) created, %d already existing skipped.',
            $result['created'],
            $result['skipped']
        ));

        return Command::SUCCESS;
    }
}
