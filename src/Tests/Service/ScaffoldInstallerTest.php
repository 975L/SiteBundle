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

// Lives under src/Tests (not a sibling tests/ dir) so it stays autoloadable by consuming apps,
// whose attribute route loader recursively reflects every class under the bundle root
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

        $this->assertSame(['copied' => 2, 'backedUp' => 0], $result);
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

        $this->assertSame(['copied' => 1, 'backedUp' => 1], $result);
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

        $this->assertSame(['copied' => 2, 'backedUp' => 0], $result);
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

        $this->assertSame(['copied' => 0, 'backedUp' => 0], $installer->install());
    }
}
