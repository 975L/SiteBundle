<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Repository\FontRepository;
use c975L\SiteBundle\Service\FontService;
use PHPUnit\Framework\TestCase;

class FontServiceTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/font-service-test-' . uniqid();
        mkdir($this->projectDir . '/assets/styles', 0775, true);
    }

    protected function tearDown(): void
    {
        foreach (glob($this->projectDir . '/assets/styles/*') ?: [] as $file) {
            unlink($file);
        }
        rmdir($this->projectDir . '/assets/styles');
        rmdir($this->projectDir . '/assets');
        rmdir($this->projectDir);
    }

    private function createService(?string $configuredPath = '/assets/styles/_fonts.css', array $uploadedNames = []): FontService
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($configuredPath);

        $fontRepository = $this->createStub(FontRepository::class);
        $fontRepository->method('findDistinctNames')->willReturn($uploadedNames);

        return new FontService($configService, $fontRepository, $this->projectDir);
    }

    public function testGetFontsExtractsAndSortsUniqueFontFamilyNames(): void
    {
        file_put_contents($this->projectDir . '/assets/styles/_fonts.css', <<<'CSS'
            @font-face {
                font-family: "Roboto";
                src: url("./fonts/roboto.woff2") format("woff2");
            }
            @font-face {
                font-family: 'Georgia';
                src: url("./fonts/georgia.woff2") format("woff2");
            }
            @font-face {
                font-family: Roboto;
                font-weight: 700;
            }
            CSS);

        $this->assertSame(['Georgia', 'Roboto'], $this->createService()->getFonts());
    }

    // A commented-out example (see scaffold/assets/styles/_fonts.css) must never be offered as a real, unusable choice
    public function testGetFontsIgnoresCommentedOutFontFace(): void
    {
        file_put_contents($this->projectDir . '/assets/styles/_fonts.css', <<<'CSS'
            /*
            @font-face {
                font-family: "Example Sans";
            }
            */
            CSS);

        $this->assertSame([], $this->createService()->getFonts());
    }

    public function testGetFontsReturnsEmptyArrayWhenFileIsMissing(): void
    {
        $this->assertSame([], $this->createService('/assets/styles/does-not-exist.css')->getFonts());
    }

    public function testGetFontsFallsBackToDefaultPathWhenConfigIsNull(): void
    {
        file_put_contents($this->projectDir . '/assets/styles/_fonts.css', '@font-face { font-family: "Georgia"; }');

        $this->assertSame(['Georgia'], $this->createService(null)->getFonts());
    }

    // Admin-uploaded fonts (see FontCrudController/FontCssListener) are offered alongside the dev-declared ones
    public function testGetFontsMergesUploadedFontNamesWithDeclaredOnes(): void
    {
        file_put_contents($this->projectDir . '/assets/styles/_fonts.css', '@font-face { font-family: "Georgia"; }');

        $this->assertSame(
            ['Georgia', 'Roboto'],
            $this->createService(uploadedNames: ['Roboto'])->getFonts()
        );
    }

    public function testGetFontsDeduplicatesNamesPresentInBothSources(): void
    {
        file_put_contents($this->projectDir . '/assets/styles/_fonts.css', '@font-face { font-family: "Georgia"; }');

        $this->assertSame(
            ['Georgia'],
            $this->createService(uploadedNames: ['Georgia'])->getFonts()
        );
    }
}
