<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Twig;

use c975L\SiteBundle\Twig\ThemeVariablesExtension;
use PHPUnit\Framework\TestCase;

class ThemeVariablesExtensionTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/theme-variables-extension-test-' . uniqid();
        mkdir($this->projectDir . '/public/bundles/build', 0777, true);
    }

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

    public function testGetThemeVariablesCssReturnsTheCompiledFileContent(): void
    {
        file_put_contents($this->projectDir . '/public/bundles/build/site-theme.css', ':root { --c975l-color-primary: #ff0000; }');

        $extension = new ThemeVariablesExtension($this->projectDir);

        $this->assertSame(':root { --c975l-color-primary: #ff0000; }', $extension->getThemeVariablesCss());
    }

    // On a fresh install, the listener may not have generated the file yet
    public function testGetThemeVariablesCssReturnsEmptyStringWhenFileIsMissing(): void
    {
        $extension = new ThemeVariablesExtension($this->projectDir);

        $this->assertSame('', $extension->getThemeVariablesCss());
    }

    public function testGetFunctionsRegistersThemeVariablesCssFunctionAsHtmlSafe(): void
    {
        $extension = new ThemeVariablesExtension($this->projectDir);

        $functions = $extension->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertSame('theme_variables_css', $functions[0]->getName());
        $this->assertSame(['html'], $functions[0]->getSafe(new \Twig\Node\TextNode('', 0)));
    }
}
