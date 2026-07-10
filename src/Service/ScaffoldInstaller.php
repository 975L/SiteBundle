<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

class ScaffoldInstaller
{
    private const SCAFFOLD_DIRS = ['src', 'templates'];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    // Copies scaffold/{src,templates} from every installed c975L bundle into the project,
    // backing up any file it would overwrite into existingFiles/<same relative path>.old
    // instead of silently erasing it (replaces the blind `cp -r` previously done by SymfonyNewProject.sh)
    public function install(): array
    {
        $copied = 0;
        $backedUp = 0;

        foreach (glob($this->projectDir . '/vendor/c975l/*') ?: [] as $bundleDir) {
            foreach (self::SCAFFOLD_DIRS as $dir) {
                $scaffoldDir = $bundleDir . '/scaffold/' . $dir;
                if (!is_dir($scaffoldDir)) {
                    continue;
                }

                $finder = (new Finder())->files()->in($scaffoldDir);
                foreach ($finder as $file) {
                    $relativePath = $dir . '/' . $file->getRelativePathname();
                    $target = $this->projectDir . '/' . $relativePath;

                    if (is_file($target)) {
                        $this->backup($relativePath, $target);
                        $backedUp++;
                    }

                    if (!is_dir(\dirname($target))) {
                        mkdir(\dirname($target), 0775, true);
                    }
                    copy($file->getPathname(), $target);
                    $copied++;
                }
            }
        }

        $this->ensureGitignored();

        return ['copied' => $copied, 'backedUp' => $backedUp];
    }

    private function backup(string $relativePath, string $target): void
    {
        $backupPath = $this->projectDir . '/existingFiles/' . $relativePath . '.old';
        if (!is_dir(\dirname($backupPath))) {
            mkdir(\dirname($backupPath), 0775, true);
        }
        rename($target, $backupPath);
    }

    private function ensureGitignored(): void
    {
        $gitignore = $this->projectDir . '/.gitignore';
        $content = is_file($gitignore) ? file_get_contents($gitignore) : '';

        if (!str_contains($content, 'existingFiles/')) {
            file_put_contents($gitignore, rtrim($content) . "\n\nexistingFiles/\n");
        }
    }
}
