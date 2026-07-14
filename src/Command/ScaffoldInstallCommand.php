<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Command;

use c975L\SiteBundle\Service\ScaffoldInstaller;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

// Standalone, re-runnable equivalent of the scaffold-install step of c975l:site:create (which is
// gated by a one-time lock file). Meant to pull in a bundle's scaffold/{src,templates,tests,translations}
// after installing it into an *existing* site (e.g. "composer require c975l/contactform-bundle" later on) -
// ScaffoldInstaller is idempotent, so running this again on an unmodified project is a no-op.
#[AsCommand(
    name: 'c975l:scaffold:install',
    description: 'Installs (or refreshes) every installed c975L bundle\'s scaffold files into the project'
)]
class ScaffoldInstallCommand extends Command
{
    public function __construct(
        private readonly ScaffoldInstaller $scaffoldInstaller,
    ) {
        parent::__construct();
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);

        $result = $this->scaffoldInstaller->install();

        $io->success(sprintf(
            '%d fichier(s) copié(s), %d sauvegardé(s) dans existingFiles/, %d déjà à jour.',
            $result['copied'],
            $result['backedUp'],
            $result['skipped']
        ));

        return Command::SUCCESS;
    }
}
