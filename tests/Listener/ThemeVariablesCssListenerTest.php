<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Listener;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\SiteBundle\Listener\ThemeVariablesCssListener;
use c975L\UiBundle\CacheWarmer\StylesheetCacheWarmer;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class ThemeVariablesCssListenerTest extends TestCase
{
    private string $projectDir;
    private string $cssPath;

    // Sandboxes each test behind its own throwaway project directory, so real filesystem writes can be exercised safely
    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/theme-variables-css-listener-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0777, true);
        $this->cssPath = $this->projectDir . '/public/bundles/build/site-theme.css';
    }

    // Leaves no trace of the sandbox project directory once the test finishes
    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

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

    private function config(string $slug, ?string $value, string $group = Config::GROUP_THEME): Config
    {
        return (new Config())->setSlug($slug)->setValue($value)->setGroup($group);
    }

    private function createListener(array $themeConfigs): ThemeVariablesCssListener
    {
        $repository = $this->createStub(ConfigRepository::class);
        $repository->method('findByGroup')->willReturn($themeConfigs);

        return new ThemeVariablesCssListener(
            $repository,
            $this->createStub(StylesheetCacheWarmer::class),
            $this->projectDir,
        );
    }

    public function testPostPersistIgnoresNonThemeConfig(): void
    {
        $listener = $this->createListener([]);

        $args = new PostPersistEventArgs(
            $this->config('site-name', 'My Site', Config::GROUP_GENERAL),
            $this->createStub(EntityManagerInterface::class),
        );
        $listener->postPersist($args);

        $this->assertFileDoesNotExist($this->cssPath);
    }

    public function testPostPersistIgnoresNonConfigEntities(): void
    {
        $listener = $this->createListener([]);

        $args = new PostPersistEventArgs(new \stdClass(), $this->createStub(EntityManagerInterface::class));
        $listener->postPersist($args);

        $this->assertFileDoesNotExist($this->cssPath);
    }

    public function testPostPersistRegeneratesFileWithCssCustomProperties(): void
    {
        $listener = $this->createListener([
            $this->config('theme-color-primary', '#ff0000'),
            $this->config('theme-font-family-title', '"Georgia", serif'),
        ]);

        $args = new PostPersistEventArgs(
            $this->config('theme-color-primary', '#ff0000'),
            $this->createStub(EntityManagerInterface::class),
        );
        $listener->postPersist($args);

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('--c975l-color-primary: #ff0000;', $css);
        $this->assertStringContainsString('--c975l-font-family-title: "Georgia", serif;', $css);
    }

    // A bare custom font name (as chosen via the new ChoiceField) gets its slug's generic fallback appended, so the
    // browser has somewhere to go if the @font-face 404s/is slow instead of falling through to no font at all
    public function testRegenerateAppendsGenericFallbackToABareCustomFontName(): void
    {
        $listener = $this->createListener([
            $this->config('theme-font-family-title', 'Roboto'),
            $this->config('theme-font-family-accent', 'Fira Code'),
        ]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->config('theme-font-family-title', 'Roboto'),
            $this->createStub(EntityManagerInterface::class),
        ));

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('--c975l-font-family-title: Roboto, sans-serif;', $css);
        $this->assertStringContainsString('--c975l-font-family-accent: Fira Code, monospace;', $css);
    }

    // A value already picked as one of Config::GENERIC_FONT_FAMILIES never needs a fallback suffix appended to itself
    public function testRegenerateDoesNotAppendFallbackWhenValueIsAlreadyAGeneric(): void
    {
        $listener = $this->createListener([
            $this->config('theme-font-family-body', 'sans-serif'),
        ]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->config('theme-font-family-body', 'sans-serif'),
            $this->createStub(EntityManagerInterface::class),
        ));

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('--c975l-font-family-body: sans-serif;', $css);
        $this->assertStringNotContainsString('sans-serif, sans-serif', $css);
    }

    // A value already containing a comma is a full stack an admin already typed by hand before this kind existed
    // (e.g. '"Georgia", serif') - left untouched, not doubled up with another fallback
    public function testRegenerateDoesNotAppendFallbackWhenValueAlreadyHasOne(): void
    {
        $listener = $this->createListener([
            $this->config('theme-font-family-title', '"Georgia", serif'),
        ]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->config('theme-font-family-title', '"Georgia", serif'),
            $this->createStub(EntityManagerInterface::class),
        ));

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('--c975l-font-family-title: "Georgia", serif;', $css);
    }

    // Empty/null values are skipped, so the SCSS fallback default keeps applying instead of an empty custom property value
    public function testRegenerateSkipsEmptyAndNullValues(): void
    {
        $listener = $this->createListener([
            $this->config('theme-color-primary', null),
            $this->config('theme-color-secondary', ''),
            $this->config('theme-color-background', '#fff'),
        ]);

        $listener->postUpdate(new PostUpdateEventArgs(
            $this->config('theme-color-background', '#fff'),
            $this->createStub(EntityManagerInterface::class),
        ));

        $css = file_get_contents($this->cssPath);
        $this->assertStringNotContainsString('--c975l-color-primary', $css);
        $this->assertStringNotContainsString('--c975l-color-secondary', $css);
        $this->assertStringContainsString('--c975l-color-background: #fff;', $css);
    }

    // theme-mode drives the server-side data-theme attribute (layout.html.twig), not a CSS value
    public function testRegenerateExcludesThemeModeSlug(): void
    {
        $listener = $this->createListener([
            $this->config('theme-mode', 'dark'),
            $this->config('theme-color-primary', '#ff0000'),
        ]);

        $listener->postUpdate(new PostUpdateEventArgs(
            $this->config('theme-color-primary', '#ff0000'),
            $this->createStub(EntityManagerInterface::class),
        ));

        $css = file_get_contents($this->cssPath);
        $this->assertStringNotContainsString('theme-mode', $css);
        $this->assertStringNotContainsString('--c975l-mode', $css);
    }

    // site-fonts-face-file is a PHP-side path read by FontService, never a CSS custom property - also not
    // "theme-"-prefixed, exercising the mechanical slug->variable mapping's non-prefixed branch
    public function testRegenerateExcludesSiteFontsFaceFileSlug(): void
    {
        $listener = $this->createListener([
            $this->config('site-fonts-face-file', '/assets/styles/_fonts.css'),
            $this->config('theme-color-primary', '#ff0000'),
        ]);

        $listener->postUpdate(new PostUpdateEventArgs(
            $this->config('theme-color-primary', '#ff0000'),
            $this->createStub(EntityManagerInterface::class),
        ));

        $css = file_get_contents($this->cssPath);
        $this->assertStringNotContainsString('_fonts.css', $css);
        $this->assertStringNotContainsString('site-fonts-face-file', $css);
    }

    public function testPostRemoveRegeneratesFileReflectingTheRemainingConfigs(): void
    {
        $listener = $this->createListener([
            $this->config('theme-color-secondary', '#00ff00'),
        ]);

        $listener->postRemove(new PostRemoveEventArgs(
            $this->config('theme-color-primary', '#ff0000'),
            $this->createStub(EntityManagerInterface::class),
        ));

        $css = file_get_contents($this->cssPath);
        $this->assertStringNotContainsString('--c975l-color-primary', $css);
        $this->assertStringContainsString('--c975l-color-secondary: #00ff00;', $css);
    }

    public function testRegenerateCreatesTheBuildDirectoryWhenMissing(): void
    {
        $listener = $this->createListener([]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->config('theme-color-primary', '#ff0000'),
            $this->createStub(EntityManagerInterface::class),
        ));

        $this->assertDirectoryExists($this->projectDir . '/public/bundles/build');
    }

    public function testRegenerateWritesAnEmptyFileWhenNoThemeValueIsSet(): void
    {
        $listener = $this->createListener([
            $this->config('theme-color-primary', null),
        ]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->config('theme-color-primary', null),
            $this->createStub(EntityManagerInterface::class),
        ));

        $this->assertSame('', file_get_contents($this->cssPath));
    }

    // Guards against configs persisted before this listener existed (or restored from a backup) that never fire another Doctrine event on their own - cache:warmup/cache:clear must still produce an up-to-date file
    public function testWarmUpRegeneratesFileFromCurrentConfigs(): void
    {
        $listener = $this->createListener([
            $this->config('theme-color-primary', '#ff0000'),
        ]);

        $result = $listener->warmUp($this->projectDir);

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('--c975l-color-primary: #ff0000;', $css);
        $this->assertSame([], $result);
    }

    // Regression test: in prod, the real site links UiBundle's concatenated bundles/build/site.css, not site-theme.css directly - without this call, applying a preset would regenerate site-theme.css but the live site would keep serving the stale site.css until the next warmup
    public function testRegenerateRecompilesTheConcatenatedStylesheet(): void
    {
        $repository = $this->createStub(ConfigRepository::class);
        $repository->method('findByGroup')->willReturn([$this->config('theme-color-primary', '#ff0000')]);

        $stylesheetCacheWarmer = $this->createMock(StylesheetCacheWarmer::class);
        $stylesheetCacheWarmer->expects($this->once())->method('compileAll');

        $listener = new ThemeVariablesCssListener($repository, $stylesheetCacheWarmer, $this->projectDir);
        $listener->postUpdate(new PostUpdateEventArgs(
            $this->config('theme-color-primary', '#ff0000'),
            $this->createStub(EntityManagerInterface::class),
        ));
    }

    public function testIsOptional(): void
    {
        $listener = $this->createListener([]);

        $this->assertTrue($listener->isOptional());
    }
}
