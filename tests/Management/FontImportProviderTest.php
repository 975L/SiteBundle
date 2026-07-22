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
use c975L\SiteBundle\Management\FontImportProvider;
use c975L\SiteBundle\Repository\FontRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class FontImportProviderTest extends TestCase
{
    private function createFontRepository(?Font $existingFont = null): FontRepository
    {
        $repository = $this->createStub(FontRepository::class);
        $repository->method('findOneBy')->willReturn($existingFont);

        return $repository;
    }

    private function createFilesDir(string $entryPath, string $content): string
    {
        $filesDir = sys_get_temp_dir() . '/font_import_test_' . bin2hex(random_bytes(4));
        mkdir(\dirname($filesDir . '/' . $entryPath), 0777, true);
        file_put_contents($filesDir . '/' . $entryPath, $content);

        return $filesDir;
    }

    public function testSupportsImportOnlyMatchesSiteFontKind(): void
    {
        $provider = new FontImportProvider($this->createStub(EntityManagerInterface::class), $this->createFontRepository());

        $this->assertTrue($provider->supportsImport('site_font'));
        $this->assertFalse($provider->supportsImport('site_page'));
    }

    public function testImportCreatesANewFontFromTheExtractedZipFile(): void
    {
        $filesDir = $this->createFilesDir('files/roboto-bold.woff2', 'fake-font-bytes');

        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $provider = new FontImportProvider($em, $this->createFontRepository());

        $result = $provider->import([[
            'name' => 'Roboto',
            'weight' => 700,
            'style' => 'normal',
            'originalFilename' => 'roboto-bold.woff2',
            'file' => 'files/roboto-bold.woff2',
        ]], $filesDir);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);
        $this->assertCount(1, $persisted);

        $font = $persisted[0];
        $this->assertSame('Roboto', $font->getName());
        $this->assertSame(700, $font->getWeight());

        $file = $font->getFile();
        $this->assertNotNull($file);
        $this->assertSame($filesDir . '/files/roboto-bold.woff2', $file->getPathname());
        $this->assertSame('fake-font-bytes', file_get_contents($file->getPathname()));

        unlink($file->getPathname());
        rmdir($filesDir . '/files');
        rmdir($filesDir);
    }

    public function testImportOverwritesAnExistingFontsFile(): void
    {
        $filesDir = $this->createFilesDir('files/roboto-regular.woff2', 'new-font-bytes');
        $existing = (new Font())->setName('Roboto')->setWeight(400)->setStyle('normal');

        $provider = new FontImportProvider($this->createStub(EntityManagerInterface::class), $this->createFontRepository($existing));

        $result = $provider->import([[
            'name' => 'Roboto',
            'weight' => 400,
            'style' => 'normal',
            'originalFilename' => 'roboto-regular.woff2',
            'file' => 'files/roboto-regular.woff2',
        ]], $filesDir);

        $this->assertSame(['created' => 0, 'updated' => 1], $result);

        $file = $existing->getFile();
        $this->assertNotNull($file);
        $this->assertSame('new-font-bytes', file_get_contents($file->getPathname()));

        unlink($file->getPathname());
        rmdir($filesDir . '/files');
        rmdir($filesDir);
    }
}
