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
    private const SCAFFOLD_DIRS = ['src', 'templates', 'tests', 'translations', 'assets'];

    public function __construct(
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    // Copies scaffold/{src,templates,tests,translations,assets} from every installed c975L bundle into
    // the project, backing up any file it would overwrite into existingFiles/<same relative path>.old
    // instead of silently erasing it - a target already identical to the scaffold source is left
    // untouched (no backup, no copy), so re-running this on an unmodified project is a no-op. 'assets'
    // is the one exception: once a target exists there, it's left alone even if its content now
    // differs from the bundle's - it's the app's own editable file from then on (see themeImportReminder())
    public function install(): array
    {
        $copied = 0;
        $backedUp = 0;
        $skipped = 0;

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
                        // 'assets' is the app's own editable copy from the first install onward (see
                        // themeImportReminder()) - unlike src/templates/tests/translations, it's never
                        // backed up/overwritten again once it exists, whether its content differs or not
                        if ('assets' === $dir) {
                            $skipped++;
                            continue;
                        }
                        if (file_get_contents($target) === file_get_contents($file->getPathname())) {
                            $skipped++;
                            continue;
                        }
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

        return ['copied' => $copied, 'backedUp' => $backedUp, 'skipped' => $skipped];
    }

    // Never writes to the app's own assets/styles/app.css - editing a developer-owned file in place is
    // too risky to automate reliably. Returns a one-line reminder for the calling command to display
    // when the scaffolded assets/styles/themes/theme.css exists but app.css doesn't import it yet, null
    // otherwise (already wired, or this project doesn't have either file)
    public function themeImportReminder(): ?string
    {
        $themeFile = $this->projectDir . '/assets/styles/themes/theme.css';
        $appCss = $this->projectDir . '/assets/styles/app.css';
        if (!is_file($themeFile) || !is_file($appCss)) {
            return null;
        }

        if (str_contains(file_get_contents($appCss), 'themes/theme.css')) {
            return null;
        }

        return 'Add @import url("./themes/theme.css"); to assets/styles/app.css (before any non-@import rule) to activate your site\'s editable theme.';
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

    // Wires the scaffolded assets/styles/themes/theme.css (the app's own, freely-editable theme -
    // see scaffold/assets/styles/themes/theme.css) into app.css, so a fresh scaffold install doesn't
    // leave it orphaned. @import must precede other rules to be valid CSS, so this is inserted right
    // after the last existing @import rather than appended at the end
    private function ensureThemeImport(): void
    {
        $themeFile = $this->projectDir . '/assets/styles/themes/theme.css';
        $appCss = $this->projectDir . '/assets/styles/app.css';
        if (!is_file($themeFile) || !is_file($appCss)) {
            return;
        }

        $content = file_get_contents($appCss);
        if (str_contains($content, 'themes/theme.css')) {
            return;
        }

        $import = "/* THEME */\n@import url(\"./themes/theme.css\");";
        $lines = explode("\n", $content);
        $lastImportLine = null;
        foreach ($lines as $i => $line) {
            if (str_starts_with(ltrim($line), '@import')) {
                $lastImportLine = $i;
            }
        }

        if (null === $lastImportLine) {
            file_put_contents($appCss, $import . "\n\n" . $content);
            return;
        }

        array_splice($lines, $lastImportLine + 1, 0, ['', $import]);
        file_put_contents($appCss, implode("\n", $lines));
    }
}
