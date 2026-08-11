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
        foreach ([...glob($this->projectDir . '/assets/styles/themes/*') ?: [], ...glob($this->projectDir . '/assets/styles/*.css') ?: []] as $file) {
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

        $stylesheets = new ThemeStylesheetProvider($this->projectDir)->getStylesheets();

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

        $stylesheets = new ThemeStylesheetProvider($this->projectDir)->getStylesheets();

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

        $stylesheets = new ThemeStylesheetProvider($this->projectDir)->getStylesheets();

        $this->assertSame(['assets/styles/themes/site.css'], $stylesheets);
    }

    // A project the scaffold has not run on yet: no directory at all, and the registry gets an empty list rather than an error
    public function testAProjectWithoutAnyThemeDirectoryContributesNothing(): void
    {
        rmdir($this->projectDir . '/assets/styles/themes');

        $this->assertSame([], new ThemeStylesheetProvider($this->projectDir)->getStylesheets());
    }

    // The site's own sheets are registered here too rather than imported from assets/app.js, which would put them in the import map, where AssetMapper addresses a CSS entry by a "data:application/javascript," URL the site's Content-Security-Policy blocks
    public function testTheSiteOwnStylesheetsAreContributedAfterTheThemes(): void
    {
        $this->addTheme('ui.css');
        $this->addStylesheet('app.css');

        $stylesheets = new ThemeStylesheetProvider($this->projectDir)->getStylesheets();

        $this->assertSame([
            'assets/styles/themes/ui.css',
            'assets/styles/app.css',
        ], $stylesheets);
    }

    // app.css is where the design has the final word, so it has to be what the cascade reads last - a sheet sorting after it must not push it up
    public function testTheAppStylesheetIsContributedLastWhateverTheAlphabetSays(): void
    {
        $this->addStylesheet('_block-showcase.css');
        $this->addStylesheet('app.css');
        $this->addStylesheet('typography.css');

        $stylesheets = new ThemeStylesheetProvider($this->projectDir)->getStylesheets();

        $this->assertSame([
            'assets/styles/_block-showcase.css',
            'assets/styles/typography.css',
            'assets/styles/app.css',
        ], $stylesheets);
    }

    private function addTheme(string $name): void
    {
        file_put_contents($this->projectDir . '/assets/styles/themes/' . $name, ':root {}');
    }

    private function addStylesheet(string $name): void
    {
        file_put_contents($this->projectDir . '/assets/styles/' . $name, ':root {}');
    }
}
