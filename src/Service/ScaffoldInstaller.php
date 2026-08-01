<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\UiBundle\Entity\Media;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Finder\Finder;

class ScaffoldInstaller
{
    private const SCAFFOLD_DIRS = ['src', 'templates', 'tests', 'translations', 'assets'];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    // Copies scaffold/{src,templates,tests,translations,assets} from every installed c975L bundle into the project, backing up any file it would overwrite into existingFiles/<same relative path>.old instead of silently erasing it - a target already identical to the scaffold source is left untouched (no backup, no copy), so re-running this on an unmodified project is a no-op. 'assets' is the one exception: once a target exists there, it's left alone even if its content now differs from the bundle's - it's the app's own editable file from then on (see themeImportReminder()). $paths restricts the run to the relative paths given ('src/Scheduler', or a single file), $dryRun reports what would happen without writing anything - propagating one upgraded scaffold file across many sites otherwise means passing over every other file they may have diverged on. The returned 'unmatched' lists the given paths no scaffold file answered to, a run restricted to nothing at all being indistinguishable from an up-to-date site otherwise
    public function install(array $paths = [], bool $dryRun = false): array
    {
        $copied = 0;
        $backedUp = 0;
        $skipped = 0;
        $files = [];
        $matchedPaths = [];

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

                    if ($paths) {
                        $matched = array_filter($paths, static fn (string $path): bool => self::matches($relativePath, $path));
                        if (!$matched) {
                            continue;
                        }

                        // Kept whatever the file's fate is (copied, backed up, or already identical): the path did name something, which is all the caller needs to tell a real no-op from a typo
                        $matchedPaths = array_merge($matchedPaths, $matched);
                    }

                    if (is_file($target)) {
                        // 'assets' is the app's own editable copy from the first install onward (see themeImportReminder()) - unlike src/templates/tests/translations, it's never backed up/overwritten again once it exists, whether its content differs or not
                        if ('assets' === $dir) {
                            ++$skipped;
                            continue;
                        }
                        if (file_get_contents($target) === file_get_contents($file->getPathname())) {
                            ++$skipped;
                            continue;
                        }
                        if (!$dryRun) {
                            $this->backup($relativePath, $target);
                        }
                        ++$backedUp;
                    }

                    if (!$dryRun) {
                        if (!is_dir(\dirname($target))) {
                            mkdir(\dirname($target), 0775, true);
                        }
                        copy($file->getPathname(), $target);
                    }
                    ++$copied;
                    $files[] = $relativePath;
                }
            }
        }

        if (!$dryRun) {
            $this->ensureGitignored();
        }

        return [
            'copied' => $copied,
            'backedUp' => $backedUp,
            'skipped' => $skipped,
            'files' => $files,
            'unmatched' => array_values(array_diff($paths, $matchedPaths)),
        ];
    }

    // A file is in scope when the path is the file itself or a directory it sits under - 'src/Scheduler' must not match 'src/SchedulerOther/...', hence the separator
    private static function matches(string $relativePath, string $path): bool
    {
        $path = trim($path, '/');

        return $relativePath === $path || str_starts_with($relativePath, $path . '/');
    }

    // Never writes to the app's own assets/styles/app.css or assets/app.js - editing a developer-owned file in place is too risky to automate reliably. Returns a one-line reminder for the calling command to display when the scaffolded assets/styles/themes/theme.css exists but nothing imports it yet, null otherwise (already wired, or this project doesn't have either file)
    public function themeImportReminder(): ?string
    {
        if (!is_file($this->projectDir . '/assets/styles/themes/theme.css')) {
            return null;
        }

        // assets/app.js counts too, and is in fact the better place: AssetMapper doesn't merge CSS, so an @import forces the browser to download and parse app.css before it even discovers the theme, while two imports from app.js are fetched in parallel. Either file alone is enough to wire the theme, so an app.js-only project gets the reminder just like an app.css one - only a project with neither has nowhere to put the import, and nothing to be reminded of
        $wiringPoints = array_filter([
            $this->projectDir . '/assets/styles/app.css',
            $this->projectDir . '/assets/app.js',
        ], 'is_file');

        if (!$wiringPoints) {
            return null;
        }

        foreach ($wiringPoints as $path) {
            if (str_contains(file_get_contents($path), 'themes/theme.css')) {
                return null;
            }
        }

        return 'Import the theme to activate it: add "import \'./styles/themes/theme.css\';" to assets/app.js, before the ./styles/app.css import (preferred - AssetMapper doesn\'t merge CSS, so both sheets are then fetched in parallel), or @import url("./themes/theme.css"); at the top of assets/styles/app.css.';
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

        $missing = array_values(array_filter(
            $this->gitignoreRules(),
            static fn (string $rule): bool => !str_contains($content, $rule)
        ));

        if ($missing) {
            file_put_contents($gitignore, rtrim($content) . "\n\n" . implode("\n", $missing) . "\n");
        }
    }

    // What the back-office writes into the project at runtime, carried between environments by the export/import (see SiteGraphicExportProvider/SiteGraphicImportProvider), never by git: tracking any of it means the next deploy resetting the working tree wipes whatever was uploaded in production. Singleton graphics sit at the root of public/ under their own role name, with the extension the role's fixed spec imposes (see c975L\UiBundle\Namer\UiMediaNamer), hence the wildcard rather than a hardcoded list of filenames
    private function gitignoreRules(): array
    {
        $rules = ['existingFiles/', 'public/medias'];
        foreach (Media::getSingletonRoles() as $role) {
            $rules[] = 'public/' . $role . '.*';
        }

        return $rules;
    }
}
