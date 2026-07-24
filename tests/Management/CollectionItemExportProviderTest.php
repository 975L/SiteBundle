<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Management\CollectionItemExportProvider;
use c975L\SiteBundle\Management\CollectionItemImportProvider;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use PHPUnit\Framework\TestCase;

class CollectionItemExportProviderTest extends TestCase
{
    private function collectionGroup(string $name): CollectionGroup
    {
        return (new CollectionGroup())->setName($name)->setSlug(strtolower($name));
    }

    public function testGetKindMatchesCollectionItemImportProvider(): void
    {
        $provider = new CollectionItemExportProvider($this->createStub(CollectionItemRepository::class), sys_get_temp_dir());

        $this->assertSame(CollectionItemImportProvider::KIND, $provider->getKind());
    }

    public function testExportAllSerializesEveryCollectionItemFromTheRepository(): void
    {
        $projectDir = sys_get_temp_dir() . '/collection_item_export_provider_test_' . bin2hex(random_bytes(4));
        mkdir($projectDir . '/public/uploads', 0777, true);
        $filename = 'uploads/project-cover.jpg';
        file_put_contents($projectDir . '/public/' . $filename, 'fake-image-bytes');

        $item = (new CollectionItem())
            ->setCollectionGroup($this->collectionGroup('Projects'))
            ->setTitle('My Project')
            ->setSlug('my-project')
            ->setDescription('A description')
            ->setUrl('https://example.com')
            ->setPosition(2)
            ->setFilename($filename);

        $collectionItemRepository = $this->createStub(CollectionItemRepository::class);
        $collectionItemRepository->method('findBy')->willReturn([$item]);

        $data = (new CollectionItemExportProvider($collectionItemRepository, $projectDir))->exportAll();

        $this->assertSame('Projects', $data['items'][0]['collectionGroup']);
        $this->assertSame('my-project', $data['items'][0]['slug']);
        $this->assertSame(2, $data['items'][0]['position']);
        $this->assertSame('project-cover.jpg', $data['items'][0]['originalFilename']);
        $this->assertCount(1, $data['files']);
        $this->assertSame($projectDir . '/public/' . $filename, array_values($data['files'])[0]);

        unlink($projectDir . '/public/' . $filename);
        rmdir($projectDir . '/public/uploads');
        rmdir($projectDir . '/public');
        rmdir($projectDir);
    }

    public function testSerializeExportsAnItemWithNoFileWithoutAFileEntry(): void
    {
        $item = (new CollectionItem())
            ->setCollectionGroup($this->collectionGroup('Projects'))
            ->setTitle('No Image')
            ->setSlug('no-image')
            ->setPosition(0);

        $data = (new CollectionItemExportProvider($this->createStub(CollectionItemRepository::class), sys_get_temp_dir()))->serialize([$item]);

        $this->assertSame('no-image', $data['items'][0]['slug']);
        $this->assertArrayNotHasKey('file', $data['items'][0]);
        $this->assertSame([], $data['files']);
    }

    public function testSerializeExportsAnItemWithAnUnreadableFileWithoutAFileEntry(): void
    {
        $item = (new CollectionItem())
            ->setCollectionGroup($this->collectionGroup('Projects'))
            ->setTitle('Ghost')
            ->setSlug('ghost')
            ->setPosition(0)
            ->setFilename('uploads/missing.jpg');

        $data = (new CollectionItemExportProvider($this->createStub(CollectionItemRepository::class), sys_get_temp_dir()))->serialize([$item]);

        $this->assertArrayNotHasKey('file', $data['items'][0]);
        $this->assertSame([], $data['files']);
    }
}
