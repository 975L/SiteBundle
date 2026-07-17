<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Service\ScaffoldInstaller;
use PHPUnit\Framework\TestCase;

class ScaffoldInstallerTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/c975l-scaffold-installer-test-' . uniqid();
        mkdir($this->projectDir, 0775, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    // Recursively deletes a directory tree (no external dependency needed for this test-only cleanup)
    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    // Fabricates vendor/c975l/<bundleName>/scaffold/{src,templates} with the given file => content map
    private function addScaffoldBundle(string $bundleName, array $files): void
    {
        foreach ($files as $relativePath => $content) {
            $target = $this->projectDir . '/vendor/c975l/' . $bundleName . '/scaffold/' . $relativePath;
            mkdir(\dirname($target), 0775, true);
            file_put_contents($target, $content);
        }
    }

    // A brand-new project with no pre-existing files: every scaffold file is copied, nothing backed up
    public function testInstallCopiesEveryScaffoldFileWhenNoneExistYet(): void
    {
        $this->addScaffoldBundle('config-bundle', [
            'src/Controller/FooController.php' => 'foo',
            'templates/foo.html.twig' => 'bar',
        ]);
        $installer = new ScaffoldInstaller($this->projectDir);

        $result = $installer->install();

        $this->assertSame(['copied' => 2, 'backedUp' => 0, 'skipped' => 0], $result);
        $this->assertFileExists($this->projectDir . '/src/Controller/FooController.php');
        $this->assertSame('foo', file_get_contents($this->projectDir . '/src/Controller/FooController.php'));
        $this->assertFileExists($this->projectDir . '/templates/foo.html.twig');
    }

    // A file already present at the target path is backed up under existingFiles/ instead of being erased
    public function testInstallBacksUpExistingFileInsteadOfOverwritingIt(): void
    {
        $this->addScaffoldBundle('site-bundle', ['src/Kernel.php' => 'new-content']);
        mkdir($this->projectDir . '/src', 0775, true);
        file_put_contents($this->projectDir . '/src/Kernel.php', 'original-content');
        $installer = new ScaffoldInstaller($this->projectDir);

        $result = $installer->install();

        $this->assertSame(['copied' => 1, 'backedUp' => 1, 'skipped' => 0], $result);
        $this->assertSame('new-content', file_get_contents($this->projectDir . '/src/Kernel.php'));
        $this->assertSame('original-content', file_get_contents($this->projectDir . '/existingFiles/src/Kernel.php.old'));
    }

    // Scaffold files from several installed bundles are all merged into the project
    public function testInstallMergesScaffoldFilesFromEveryInstalledBundle(): void
    {
        $this->addScaffoldBundle('config-bundle', ['templates/a.html.twig' => 'a']);
        $this->addScaffoldBundle('site-bundle', ['templates/b.html.twig' => 'b']);
        $installer = new ScaffoldInstaller($this->projectDir);

        $result = $installer->install();

        $this->assertSame(['copied' => 2, 'backedUp' => 0, 'skipped' => 0], $result);
        $this->assertFileExists($this->projectDir . '/templates/a.html.twig');
        $this->assertFileExists($this->projectDir . '/templates/b.html.twig');
    }

    // .gitignore gets an existingFiles/ entry appended, but only once even across repeated installs
    public function testInstallAppendsExistingFilesToGitignoreOnlyOnce(): void
    {
        file_put_contents($this->projectDir . '/.gitignore', "vendor/\n");
        $installer = new ScaffoldInstaller($this->projectDir);

        $installer->install();
        $installer->install();

        $gitignore = file_get_contents($this->projectDir . '/.gitignore');
        $this->assertSame(1, substr_count($gitignore, 'existingFiles/'));
        $this->assertStringContainsString('vendor/', $gitignore);
    }

    // No vendor/c975l directory at all (e.g. a dry run before composer install): install() must not error out
    public function testInstallHandlesMissingVendorDirectoryGracefully(): void
    {
        $installer = new ScaffoldInstaller($this->projectDir);

        $this->assertSame(['copied' => 0, 'backedUp' => 0, 'skipped' => 0], $installer->install());
    }

    // A target already identical to the scaffold source is left untouched: no backup, no re-copy -
    // re-running install() on an unmodified project must not litter existingFiles/ with no-op backups
    public function testInstallSkipsFileAlreadyIdenticalToScaffoldSource(): void
    {
        $this->addScaffoldBundle('site-bundle', ['src/Kernel.php' => 'same-content']);
        mkdir($this->projectDir . '/src', 0775, true);
        file_put_contents($this->projectDir . '/src/Kernel.php', 'same-content');
        $installer = new ScaffoldInstaller($this->projectDir);

        $result = $installer->install();

        $this->assertSame(['copied' => 0, 'backedUp' => 0, 'skipped' => 1], $result);
        $this->assertSame('same-content', file_get_contents($this->projectDir . '/src/Kernel.php'));
        $this->assertDirectoryDoesNotExist($this->projectDir . '/existingFiles');
    }

    // Unlike src/templates/tests/translations, an existing "assets" file is never backed up/overwritten
    // again once it's there, even if the bundle's own copy has since changed - it's the app's own
    // editable file from the first install onward (e.g. a customized assets/styles/themes/theme.css)
    public function testInstallNeverOverwritesAnExistingAssetsFileEvenWhenContentDiffers(): void
    {
        $this->addScaffoldBundle('site-bundle', ['assets/styles/themes/theme.css' => ':root { --radius-btn: 0; }']);
        mkdir($this->projectDir . '/assets/styles/themes', 0775, true);
        file_put_contents($this->projectDir . '/assets/styles/themes/theme.css', ':root { --radius-btn: 999px; }');
        $installer = new ScaffoldInstaller($this->projectDir);

        $result = $installer->install();

        $this->assertSame(['copied' => 0, 'backedUp' => 0, 'skipped' => 1], $result);
        $this->assertSame(':root { --radius-btn: 999px; }', file_get_contents($this->projectDir . '/assets/styles/themes/theme.css'));
        $this->assertDirectoryDoesNotExist($this->projectDir . '/existingFiles');
    }

    // Both theme.css and app.css present, app.css doesn't import it yet: the caller gets a reminder to
    // add it by hand - install() itself never writes to app.css
    public function testThemeImportReminderIsReturnedWhenNotYetWired(): void
    {
        mkdir($this->projectDir . '/assets/styles/themes', 0775, true);
        file_put_contents($this->projectDir . '/assets/styles/themes/theme.css', ':root {}');
        file_put_contents($this->projectDir . '/assets/styles/app.css', "body { color: red; }\n");
        $installer = new ScaffoldInstaller($this->projectDir);

        $reminder = $installer->themeImportReminder();

        $this->assertNotNull($reminder);
        $this->assertStringContainsString('themes/theme.css', $reminder);
        $this->assertSame("body { color: red; }\n", file_get_contents($this->projectDir . '/assets/styles/app.css'));
    }

    // Already wired: no reminder needed
    public function testThemeImportReminderIsNullWhenAlreadyWired(): void
    {
        mkdir($this->projectDir . '/assets/styles/themes', 0775, true);
        file_put_contents($this->projectDir . '/assets/styles/themes/theme.css', ':root {}');
        file_put_contents($this->projectDir . '/assets/styles/app.css', "@import url(\"./themes/theme.css\");\n");
        $installer = new ScaffoldInstaller($this->projectDir);

        $this->assertNull($installer->themeImportReminder());
    }

    // No app.css at all (e.g. a bundle-only test fixture, or a project not using AssetMapper for CSS),
    // or no scaffolded theme.css yet: nothing to remind about
    public function testThemeImportReminderIsNullWhenEitherFileIsMissing(): void
    {
        $installer = new ScaffoldInstaller($this->projectDir);

        $this->assertNull($installer->themeImportReminder());
    }
}
