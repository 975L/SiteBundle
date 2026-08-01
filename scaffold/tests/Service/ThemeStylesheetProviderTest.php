<?php

namespace App\Tests\Service;

use App\Service\ThemeStylesheetProvider;
use PHPUnit\Framework\TestCase;

class ThemeStylesheetProviderTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/c975l-theme-stylesheet-provider-test-' . uniqid();
        mkdir($this->projectDir . '/assets/styles/themes', 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->projectDir . '/assets/styles/themes/*') ?: [] as $file) {
            unlink($file);
        }

        foreach (['/assets/styles/themes', '/assets/styles', '/assets', ''] as $dir) {
            if (is_dir($this->projectDir . $dir)) {
                rmdir($this->projectDir . $dir);
            }
        }
    }

    // Paths relative to the project, which is what the registry compiles from - an absolute one would only resolve on the machine that built it
    public function testEveryThemeFileIsContributedAsAProjectRelativePath(): void
    {
        $this->addTheme('site.css');
        $this->addTheme('ui.css');

        $stylesheets = (new ThemeStylesheetProvider($this->projectDir))->getStylesheets();

        $this->assertSame([
            'assets/styles/themes/site.css',
            'assets/styles/themes/ui.css',
        ], $stylesheets);
    }

    // The concatenated stylesheet has to be byte-identical from one build to the next, whatever order the filesystem hands the entries back in
    public function testTheFilesAreSortedRatherThanLeftInFilesystemOrder(): void
    {
        $this->addTheme('ui.css');
        $this->addTheme('shop.css');
        $this->addTheme('site.css');

        $stylesheets = (new ThemeStylesheetProvider($this->projectDir))->getStylesheets();

        $this->assertSame([
            'assets/styles/themes/shop.css',
            'assets/styles/themes/site.css',
            'assets/styles/themes/ui.css',
        ], $stylesheets);
    }

    // Whatever else the developer leaves in that directory is not a stylesheet, and adding it to the compiled one would break it
    public function testOnlyCssFilesAreContributed(): void
    {
        $this->addTheme('site.css');
        $this->addTheme('site.css.bak');
        $this->addTheme('README.md');

        $stylesheets = (new ThemeStylesheetProvider($this->projectDir))->getStylesheets();

        $this->assertSame(['assets/styles/themes/site.css'], $stylesheets);
    }

    // A project the scaffold has not run on yet: no directory at all, and the registry gets an empty list rather than an error
    public function testAProjectWithoutAnyThemeDirectoryContributesNothing(): void
    {
        rmdir($this->projectDir . '/assets/styles/themes');

        $this->assertSame([], (new ThemeStylesheetProvider($this->projectDir))->getStylesheets());
    }

    private function addTheme(string $name): void
    {
        file_put_contents($this->projectDir . '/assets/styles/themes/' . $name, ':root {}');
    }
}
