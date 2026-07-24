<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Management\SiteGraphicExportProvider;
use c975L\SiteBundle\Management\SiteGraphicImportProvider;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\MediaRepository;
use PHPUnit\Framework\TestCase;

class SiteGraphicExportProviderTest extends TestCase
{
    public function testGetKindMatchesSiteGraphicImportProvider(): void
    {
        $provider = new SiteGraphicExportProvider($this->createStub(MediaRepository::class), sys_get_temp_dir());

        $this->assertSame(SiteGraphicImportProvider::KIND, $provider->getKind());
    }

    public function testSerializeExportsAGraphicWithItsRoleAndFile(): void
    {
        $projectDir = sys_get_temp_dir() . '/site_graphic_export_provider_test_' . bin2hex(random_bytes(4));
        mkdir($projectDir . '/public', 0777, true);
        $filename = 'favicon.ico';
        file_put_contents($projectDir . '/public/' . $filename, 'fake-icon-bytes');

        $media = (new Media())->setRole(Media::ROLE_FAVICON)->setFilename($filename);

        $data = (new SiteGraphicExportProvider($this->createStub(MediaRepository::class), $projectDir))->serialize([$media]);

        $this->assertSame(Media::ROLE_FAVICON, $data['items'][0]['role']);
        $this->assertSame('favicon.ico', $data['items'][0]['originalFilename']);
        $this->assertCount(1, $data['files']);
        $this->assertSame($projectDir . '/public/' . $filename, array_values($data['files'])[0]);

        unlink($projectDir . '/public/' . $filename);
        rmdir($projectDir . '/public');
        rmdir($projectDir);
    }

    public function testSerializeSkipsAGraphicWithNoReadableFile(): void
    {
        $media = (new Media())->setRole(Media::ROLE_LOGO)->setFilename('logo.svg');

        $data = (new SiteGraphicExportProvider($this->createStub(MediaRepository::class), sys_get_temp_dir()))->serialize([$media]);

        $this->assertSame([], $data['items']);
        $this->assertSame([], $data['files']);
    }
}
