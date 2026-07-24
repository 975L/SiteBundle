<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Entity\Font;
use c975L\SiteBundle\Management\FontExportProvider;
use c975L\SiteBundle\Management\FontImportProvider;
use c975L\SiteBundle\Repository\FontRepository;
use PHPUnit\Framework\TestCase;

class FontExportProviderTest extends TestCase
{
    public function testGetKindMatchesFontImportProvider(): void
    {
        $provider = new FontExportProvider($this->createStub(FontRepository::class), sys_get_temp_dir());

        $this->assertSame(FontImportProvider::KIND, $provider->getKind());
    }

    public function testExportAllSerializesEveryFontFromTheRepository(): void
    {
        $projectDir = sys_get_temp_dir() . '/font_export_provider_test_' . bin2hex(random_bytes(4));
        mkdir($projectDir . '/public/uploads', 0777, true);
        $filename = 'uploads/roboto-bold.woff2';
        file_put_contents($projectDir . '/public/' . $filename, 'fake-font-bytes');

        $font = (new Font())->setName('Roboto')->setWeight(700)->setStyle('normal')->setFilename($filename);

        $fontRepository = $this->createStub(FontRepository::class);
        $fontRepository->method('findAllOrdered')->willReturn([$font]);

        $data = (new FontExportProvider($fontRepository, $projectDir))->exportAll();

        $this->assertSame('Roboto', $data['items'][0]['name']);
        $this->assertSame(700, $data['items'][0]['weight']);
        $this->assertSame('roboto-bold.woff2', $data['items'][0]['originalFilename']);
        $this->assertCount(1, $data['files']);
        $this->assertSame($projectDir . '/public/' . $filename, array_values($data['files'])[0]);

        unlink($projectDir . '/public/' . $filename);
        rmdir($projectDir . '/public/uploads');
        rmdir($projectDir . '/public');
        rmdir($projectDir);
    }

    public function testSerializeSkipsFontsWithNoReadableFile(): void
    {
        $font = (new Font())->setName('Ghost')->setWeight(400)->setStyle('normal')->setFilename('uploads/missing.woff2');

        $data = (new FontExportProvider($this->createStub(FontRepository::class), sys_get_temp_dir()))->serialize([$font]);

        $this->assertSame([], $data['items']);
        $this->assertSame([], $data['files']);
    }
}
