<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Management\SiteGraphicImportProvider;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class SiteGraphicImportProviderTest extends TestCase
{
    private function createFilesDir(string $entryPath, string $content): string
    {
        $filesDir = sys_get_temp_dir() . '/site_graphic_import_test_' . bin2hex(random_bytes(4));
        mkdir(\dirname($filesDir . '/' . $entryPath), 0777, true);
        file_put_contents($filesDir . '/' . $entryPath, $content);

        return $filesDir;
    }

    public function testSupportsImportOnlyMatchesSiteGraphicKind(): void
    {
        $provider = new SiteGraphicImportProvider($this->createStub(EntityManagerInterface::class), $this->createStub(MediaRepository::class));

        $this->assertTrue($provider->supportsImport('site_graphic'));
        $this->assertFalse($provider->supportsImport('site_font'));
    }

    public function testImportCreatesANewSingletonRoleGraphic(): void
    {
        $filesDir = $this->createFilesDir('files/favicon.ico', 'fake-icon-bytes');

        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $mediaRepository = $this->createStub(MediaRepository::class);
        $mediaRepository->method('findOneByRole')->willReturn(null);

        $provider = new SiteGraphicImportProvider($em, $mediaRepository);

        $result = $provider->import([[
            'role' => Media::ROLE_FAVICON,
            'originalFilename' => 'favicon.ico',
            'file' => 'files/favicon.ico',
        ]], $filesDir);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);
        $this->assertSame(Media::ROLE_FAVICON, $persisted[0]->getRole());

        unlink($filesDir . '/files/favicon.ico');
        rmdir($filesDir . '/files');
        rmdir($filesDir);
    }

    public function testImportOverwritesAnExistingSingletonRoleGraphicsFile(): void
    {
        $filesDir = $this->createFilesDir('files/logo.svg', 'new-logo-bytes');
        $existing = (new Media())->setRole(Media::ROLE_LOGO);

        $mediaRepository = $this->createStub(MediaRepository::class);
        $mediaRepository->method('findOneByRole')->willReturn($existing);

        $provider = new SiteGraphicImportProvider($this->createStub(EntityManagerInterface::class), $mediaRepository);

        $result = $provider->import([[
            'role' => Media::ROLE_LOGO,
            'originalFilename' => 'logo.svg',
            'file' => 'files/logo.svg',
        ]], $filesDir);

        $this->assertSame(['created' => 0, 'updated' => 1], $result);
        $file = $existing->getFile();
        $this->assertNotNull($file);
        $this->assertSame('new-logo-bytes', file_get_contents($file->getPathname()));

        unlink($file->getPathname());
        rmdir($filesDir . '/files');
        rmdir($filesDir);
    }

    public function testImportReplacesTheWholeErrorImagePoolInsteadOfAccumulatingDuplicates(): void
    {
        $filesDir = $this->createFilesDir('files/error-1.jpg', 'fake-error-image-bytes');

        $staleErrorImage = (new Media())->setRole(Media::ROLE_ERROR_IMAGE);

        $removed = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('remove')->willReturnCallback(static function (object $entity) use (&$removed): void {
            $removed[] = $entity;
        });

        $mediaRepository = $this->createStub(MediaRepository::class);
        $mediaRepository->method('findBy')->willReturn([$staleErrorImage]);

        $provider = new SiteGraphicImportProvider($em, $mediaRepository);

        $result = $provider->import([[
            'role' => Media::ROLE_ERROR_IMAGE,
            'originalFilename' => 'error-1.jpg',
            'file' => 'files/error-1.jpg',
        ]], $filesDir);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);
        $this->assertSame([$staleErrorImage], $removed);

        unlink($filesDir . '/files/error-1.jpg');
        rmdir($filesDir . '/files');
        rmdir($filesDir);
    }
}
