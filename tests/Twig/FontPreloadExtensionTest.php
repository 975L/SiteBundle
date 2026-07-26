<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Entity\Font;
use c975L\SiteBundle\Repository\FontRepository;
use c975L\SiteBundle\Twig\FontPreloadExtension;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Cache\Adapter\ArrayAdapter;

class FontPreloadExtensionTest extends TestCase
{
    private function createFont(string $name, string $filename, int $weight = 400, string $style = 'normal'): Font
    {
        return (new Font())
            ->setName($name)
            ->setFilename($filename)
            ->setWeight($weight)
            ->setStyle($style);
    }

    /** @param array<string, string|null> $configs */
    private function createExtension(array $configs, array $fonts): FontPreloadExtension
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(fn(string $key) => $configs[$key] ?? null);

        $fontRepository = $this->createStub(FontRepository::class);
        $fontRepository->method('findAllOrdered')->willReturn($fonts);

        return new FontPreloadExtension($configService, $fontRepository, new ArrayAdapter());
    }

    // Rendered in the <head> of every page for a result that only an admin action changes - the second render must not query again
    public function testServesTheCachedResultInsteadOfQueryingAgain(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturnCallback(
            fn (string $key) => 'theme-font-family-body' === $key ? 'Inter' : null
        );

        $fontRepository = $this->createMock(FontRepository::class);
        $fontRepository->expects($this->once())
            ->method('findAllOrdered')
            ->willReturn([$this->createFont('Inter', 'medias/fonts/inter.woff2')]);

        $extension = new FontPreloadExtension($configService, $fontRepository, new ArrayAdapter());

        $first = $extension->getFontPreloads();

        $this->assertSame($first, $extension->getFontPreloads());
        $this->assertSame('medias/fonts/inter.woff2', $first[0]['path']);
    }

    public function testReturnsTheRegularUprightFileOfEachThemeFamily(): void
    {
        $extension = $this->createExtension(
            ['theme-font-family-body' => 'Inter'],
            [
                $this->createFont('Inter', 'medias/fonts/font-1-bold.woff2', 700),
                $this->createFont('Inter', 'medias/fonts/font-1-regular.woff2'),
            ]
        );

        $this->assertSame(
            [['path' => 'medias/fonts/font-1-regular.woff2', 'type' => 'font/woff2']],
            $extension->getFontPreloads()
        );
    }

    // A variable font covers every weight on its own, so it is preferred over hunting for the 400 upright
    public function testPrefersAVariableFontOverTheRegularWeight(): void
    {
        $extension = $this->createExtension(
            ['theme-font-family-body' => 'Inter'],
            [
                $this->createFont('Inter', 'medias/fonts/font-1-variable.woff2', Font::WEIGHT_VARIABLE),
                $this->createFont('Inter', 'medias/fonts/font-1-regular.woff2'),
            ]
        );

        $this->assertSame(
            [['path' => 'medias/fonts/font-1-variable.woff2', 'type' => 'font/woff2']],
            $extension->getFontPreloads()
        );
    }

    // ThemeVariablesCssListener appends a generic fallback, so the stored value is a full font stack
    public function testUsesOnlyTheFirstFamilyOfAFontStack(): void
    {
        $extension = $this->createExtension(
            ['theme-font-family-title' => '"Playfair Display", serif'],
            [$this->createFont('Playfair Display', 'medias/fonts/font-2-regular.woff2')]
        );

        $this->assertSame(
            [['path' => 'medias/fonts/font-2-regular.woff2', 'type' => 'font/woff2']],
            $extension->getFontPreloads()
        );
    }

    public function testItalicAndNonRegularWeightsAreNeverPreloaded(): void
    {
        $extension = $this->createExtension(
            ['theme-font-family-body' => 'Inter'],
            [
                $this->createFont('Inter', 'medias/fonts/font-1-italic.woff2', 400, 'italic'),
                $this->createFont('Inter', 'medias/fonts/font-1-light.woff2', 300),
            ]
        );

        $this->assertSame([], $extension->getFontPreloads());
    }

    // Preloading is a priority hint - past a couple of files it stops meaning anything
    public function testCapsThePreloadCount(): void
    {
        $extension = $this->createExtension(
            [
                'theme-font-family-title' => 'Title',
                'theme-font-family-body' => 'Body',
                'theme-font-family-accent' => 'Accent',
            ],
            [
                $this->createFont('Title', 'medias/fonts/font-1.woff2'),
                $this->createFont('Body', 'medias/fonts/font-2.woff2'),
                $this->createFont('Accent', 'medias/fonts/font-3.woff2'),
            ]
        );

        $this->assertCount(2, $extension->getFontPreloads());
    }

    // The same variable font set on two slots must not be preloaded twice
    public function testDeduplicatesAFileSharedByTwoFamilies(): void
    {
        $extension = $this->createExtension(
            ['theme-font-family-title' => 'Inter', 'theme-font-family-body' => 'Inter'],
            [$this->createFont('Inter', 'medias/fonts/font-1.woff2')]
        );

        $this->assertCount(1, $extension->getFontPreloads());
    }

    // A family offered by the dev-declared _fonts.css (see FontService) has no Font row to point at
    public function testReturnsNothingWhenNoUploadedFontMatchesTheTheme(): void
    {
        $extension = $this->createExtension(
            ['theme-font-family-body' => 'Helvetica'],
            [$this->createFont('Inter', 'medias/fonts/font-1.woff2')]
        );

        $this->assertSame([], $extension->getFontPreloads());
    }

    public function testReturnsNothingWhenNoThemeFontIsConfigured(): void
    {
        $extension = $this->createExtension([], [$this->createFont('Inter', 'medias/fonts/font-1.woff2')]);

        $this->assertSame([], $extension->getFontPreloads());
    }

    // A "type" the browser doesn't recognise makes it drop the preload, so an unknown extension is skipped
    public function testSkipsAFileWithAnUnsupportedExtension(): void
    {
        $extension = $this->createExtension(
            ['theme-font-family-body' => 'Inter'],
            [$this->createFont('Inter', 'medias/fonts/font-1.eot')]
        );

        $this->assertSame([], $extension->getFontPreloads());
    }

    public function testMapsEachSupportedExtensionToItsMimeType(): void
    {
        foreach (['woff2' => 'font/woff2', 'woff' => 'font/woff', 'ttf' => 'font/ttf', 'otf' => 'font/otf'] as $extension => $mimeType) {
            $twigExtension = $this->createExtension(
                ['theme-font-family-body' => 'Inter'],
                [$this->createFont('Inter', 'medias/fonts/font-1.' . $extension)]
            );

            $this->assertSame($mimeType, $twigExtension->getFontPreloads()[0]['type']);
        }
    }

    public function testGetFunctionsRegistersFontPreloadsFunction(): void
    {
        $extension = $this->createExtension([], []);

        $functions = $extension->getFunctions();

        $this->assertCount(1, $functions);
        $this->assertSame('font_preloads', $functions[0]->getName());
    }
}
