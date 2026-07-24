<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Management\BlockDataImporter;
use c975L\SiteBundle\Service\DefaultPagesImporter;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class BlockDataImporterTest extends TestCase
{
    public function testBuildBlocksReturnsEmptyArrayForNoBlocksData(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $blocks = (new BlockDataImporter($em, $this->createStub(DefaultPagesImporter::class)))->buildBlocks([], null);

        $this->assertSame([], $blocks);
    }

    public function testBuildBlocksRebuildsKindPositionDataAnimationAndPersistsTheBlock(): void
    {
        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $defaultPagesImporter = $this->createMock(DefaultPagesImporter::class);
        $defaultPagesImporter->expects($this->once())
            ->method('ensureFormBlockDependenciesExist')
            ->with(['kind' => 'form', 'position' => 1, 'data' => ['name' => 'contact'], 'animation' => 'fade-in']);

        $blocks = (new BlockDataImporter($em, $defaultPagesImporter))->buildBlocks([
            ['kind' => 'form', 'position' => 1, 'data' => ['name' => 'contact'], 'animation' => 'fade-in'],
        ], null);

        $this->assertCount(1, $blocks);
        $this->assertSame('form', $blocks[0]->getKind());
        $this->assertSame(1, $blocks[0]->getPosition());
        $this->assertSame(['name' => 'contact'], $blocks[0]->getData());
        $this->assertSame('fade-in', $blocks[0]->getAnimation());
        $this->assertSame([$blocks[0]], $persisted);
    }

    public function testBuildBlocksRecursesIntoNestedContainerSlotsTwoLevelsDeep(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $blocks = (new BlockDataImporter($em, $this->createStub(DefaultPagesImporter::class)))->buildBlocks([[
            'kind' => 'flex_columns',
            'position' => 0,
            'data' => [],
            'slots' => [[
                'kind' => 'flex_columns',
                'position' => 0,
                'data' => [],
                'slots' => [[
                    'kind' => 'text',
                    'position' => 0,
                    'data' => ['content' => 'deep'],
                ]],
            ]],
        ]], null);

        $middle = $blocks[0]->getSlots()->first();
        $this->assertSame('flex_columns', $middle->getKind());

        $innermost = $middle->getSlots()->first();
        $this->assertSame('text', $innermost->getKind());
        $this->assertSame(['content' => 'deep'], $innermost->getData());
        $this->assertCount(0, $innermost->getSlots());
    }

    public function testBuildBlocksAttachesMediaBuiltFromEachBlocksMediasEntry(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $blocks = (new BlockDataImporter($em, $this->createStub(DefaultPagesImporter::class)))->buildBlocks([[
            'kind' => 'image',
            'position' => 0,
            'data' => [],
            'medias' => [[
                'role' => 'illustration',
                'alt' => 'A photo',
                'position' => 0,
            ]],
        ]], null);

        $this->assertCount(1, $blocks[0]->getMedias());
        $this->assertSame('illustration', $blocks[0]->getMedias()->first()->getRole());
        $this->assertSame('A photo', $blocks[0]->getMedias()->first()->getAlt());
    }

    public function testBuildMediaSetsFileFromTheExtractedZipWhenFilesDirAndFileArePresent(): void
    {
        $filesDir = sys_get_temp_dir() . '/block_data_importer_test_' . bin2hex(random_bytes(4));
        mkdir($filesDir . '/files', 0777, true);
        file_put_contents($filesDir . '/files/photo.jpg', 'fake-image-bytes');

        $em = $this->createStub(EntityManagerInterface::class);
        $media = (new BlockDataImporter($em, $this->createStub(DefaultPagesImporter::class)))->buildMedia([
            'role' => 'illustration',
            'alt' => 'A photo',
            'position' => 0,
            'originalFilename' => 'photo.jpg',
            'file' => 'files/photo.jpg',
        ], $filesDir);

        $file = $media->getFile();
        $this->assertNotNull($file);
        $this->assertSame($filesDir . '/files/photo.jpg', $file->getPathname());
        $this->assertSame('fake-image-bytes', file_get_contents($file->getPathname()));

        unlink($filesDir . '/files/photo.jpg');
        rmdir($filesDir . '/files');
        rmdir($filesDir);
    }

    public function testBuildMediaDoesNotSetFileWhenFilesDirIsNull(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $media = (new BlockDataImporter($em, $this->createStub(DefaultPagesImporter::class)))->buildMedia([
            'role' => 'illustration',
            'originalFilename' => 'photo.jpg',
            'file' => 'files/photo.jpg',
        ], null);

        $this->assertNull($media->getFile());
    }
}
