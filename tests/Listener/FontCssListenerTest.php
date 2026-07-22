<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Listener;

use c975L\SiteBundle\Entity\Font;
use c975L\SiteBundle\Listener\FontCssListener;
use c975L\SiteBundle\Repository\FontRepository;
use c975L\UiBundle\CacheWarmer\StylesheetCacheWarmer;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use PHPUnit\Framework\TestCase;

class FontCssListenerTest extends TestCase
{
    private string $projectDir;
    private string $cssPath;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/font-css-listener-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0777, true);
        $this->cssPath = $this->projectDir . '/public/bundles/build/site-fonts-uploaded.css';
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

    private function font(string $name, int $weight, string $style, ?string $filename): Font
    {
        $font = (new Font())->setName($name)->setWeight($weight)->setStyle($style);
        $font->setFilename($filename);

        return $font;
    }

    private function createListener(array $fonts): FontCssListener
    {
        $repository = $this->createStub(FontRepository::class);
        $repository->method('findAllOrdered')->willReturn($fonts);

        return new FontCssListener(
            $repository,
            $this->createStub(StylesheetCacheWarmer::class),
            $this->projectDir,
        );
    }

    public function testPostPersistIgnoresNonFontEntities(): void
    {
        $listener = $this->createListener([]);

        $listener->postPersist(new PostPersistEventArgs(new \stdClass(), $this->createStub(EntityManagerInterface::class)));

        $this->assertFileDoesNotExist($this->cssPath);
    }

    public function testPostPersistRegeneratesFileWithFontFaceRule(): void
    {
        $listener = $this->createListener([
            $this->font('Roboto', 700, 'italic', 'medias/fonts/font-1-abc.woff2'),
        ]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->font('Roboto', 700, 'italic', 'medias/fonts/font-1-abc.woff2'),
            $this->createStub(EntityManagerInterface::class),
        ));

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('font-family: "Roboto";', $css);
        $this->assertStringContainsString('src: url("/medias/fonts/font-1-abc.woff2") format("woff2");', $css);
        $this->assertStringContainsString('font-weight: 700;', $css);
        $this->assertStringContainsString('font-style: italic;', $css);
    }

    public function testRegenerateMapsExtensionsToTheirFontFaceFormatToken(): void
    {
        $listener = $this->createListener([
            $this->font('Alpha', 400, 'normal', 'medias/fonts/font-1.ttf'),
            $this->font('Beta', 400, 'normal', 'medias/fonts/font-2.woff'),
            $this->font('Gamma', 400, 'normal', 'medias/fonts/font-3.woff2'),
        ]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->font('Alpha', 400, 'normal', 'medias/fonts/font-1.ttf'),
            $this->createStub(EntityManagerInterface::class),
        ));

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('format("truetype")', $css);
        $this->assertStringContainsString('format("woff")', $css);
        $this->assertStringContainsString('format("woff2")', $css);
    }

    public function testRegenerateSkipsRowsWithoutFilename(): void
    {
        $listener = $this->createListener([
            $this->font('Roboto', 400, 'normal', null),
        ]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->font('Roboto', 400, 'normal', null),
            $this->createStub(EntityManagerInterface::class),
        ));

        $css = file_get_contents($this->cssPath);
        $this->assertStringNotContainsString('Roboto', $css);
    }

    public function testRegenerateEscapesDoubleQuotesInFontName(): void
    {
        $listener = $this->createListener([
            $this->font('My "Font"', 400, 'normal', 'medias/fonts/font-1.woff2'),
        ]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->font('My "Font"', 400, 'normal', 'medias/fonts/font-1.woff2'),
            $this->createStub(EntityManagerInterface::class),
        ));

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('font-family: "My \\"Font\\"";', $css);
    }

    public function testPostRemoveRegeneratesFileReflectingTheRemainingFonts(): void
    {
        $listener = $this->createListener([
            $this->font('Georgia', 400, 'normal', 'medias/fonts/font-2.woff2'),
        ]);

        $listener->postRemove(new PostRemoveEventArgs(
            $this->font('Roboto', 400, 'normal', 'medias/fonts/font-1.woff2'),
            $this->createStub(EntityManagerInterface::class),
        ));

        $css = file_get_contents($this->cssPath);
        $this->assertStringNotContainsString('Roboto', $css);
        $this->assertStringContainsString('Georgia', $css);
    }

    public function testRegenerateCreatesTheBuildDirectoryWhenMissing(): void
    {
        $listener = $this->createListener([]);

        $listener->postPersist(new PostPersistEventArgs(
            $this->font('Roboto', 400, 'normal', 'medias/fonts/font-1.woff2'),
            $this->createStub(EntityManagerInterface::class),
        ));

        $this->assertDirectoryExists($this->projectDir . '/public/bundles/build');
    }

    public function testRegenerateRecompilesTheConcatenatedStylesheet(): void
    {
        $repository = $this->createStub(FontRepository::class);
        $repository->method('findAllOrdered')->willReturn([]);

        $stylesheetCacheWarmer = $this->createMock(StylesheetCacheWarmer::class);
        $stylesheetCacheWarmer->expects($this->once())->method('compileAll');

        $listener = new FontCssListener($repository, $stylesheetCacheWarmer, $this->projectDir);
        $listener->postUpdate(new PostUpdateEventArgs(
            $this->font('Roboto', 400, 'normal', 'medias/fonts/font-1.woff2'),
            $this->createStub(EntityManagerInterface::class),
        ));
    }

    public function testIsOptional(): void
    {
        $listener = $this->createListener([]);

        $this->assertTrue($listener->isOptional());
    }

    public function testWarmUpRegeneratesFileFromCurrentFonts(): void
    {
        $listener = $this->createListener([
            $this->font('Roboto', 400, 'normal', 'medias/fonts/font-1.woff2'),
        ]);

        $result = $listener->warmUp($this->projectDir);

        $css = file_get_contents($this->cssPath);
        $this->assertStringContainsString('Roboto', $css);
        $this->assertSame([], $result);
    }
}
