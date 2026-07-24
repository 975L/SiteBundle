<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Management\BlockDataExporter;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use PHPUnit\Framework\TestCase;

class BlockDataExporterTest extends TestCase
{
    public function testExportBlocksReturnsEmptyArrayForNoBlocks(): void
    {
        $files = [];
        $data = (new BlockDataExporter(sys_get_temp_dir()))->exportBlocks([], $files);

        $this->assertSame([], $data);
        $this->assertSame([], $files);
    }

    public function testExportBlocksSerializesKindPositionDataAndAnimation(): void
    {
        $block = (new Block())->setKind('text')->setPosition(2)->setData(['content' => 'hello'])->setAnimation('fade-in');

        $files = [];
        $data = (new BlockDataExporter(sys_get_temp_dir()))->exportBlocks([$block], $files);

        $this->assertSame([[
            'kind' => 'text',
            'position' => 2,
            'data' => ['content' => 'hello'],
            'animation' => 'fade-in',
            'medias' => [],
            'slots' => [],
        ]], $data);
    }

    public function testExportBlocksRecursesIntoNestedContainerSlotsTwoLevelsDeep(): void
    {
        $innermost = (new Block())->setKind('text')->setPosition(0)->setData(['content' => 'deep']);
        $middle = (new Block())->setKind('flex_columns')->setPosition(0)->setData([]);
        $middle->addSlot($innermost);
        $outer = (new Block())->setKind('flex_columns')->setPosition(0)->setData([]);
        $outer->addSlot($middle);

        $files = [];
        $data = (new BlockDataExporter(sys_get_temp_dir()))->exportBlocks([$outer], $files);

        $middleSlots = $data[0]['slots'];
        $this->assertCount(1, $middleSlots);
        $this->assertSame('flex_columns', $middleSlots[0]['kind']);

        $innerSlots = $middleSlots[0]['slots'];
        $this->assertCount(1, $innerSlots);
        $this->assertSame('text', $innerSlots[0]['kind']);
        $this->assertSame(['content' => 'deep'], $innerSlots[0]['data']);
        $this->assertSame([], $innerSlots[0]['slots']);
    }

    public function testExportMediaReturnsNullWhenFilenameIsNull(): void
    {
        $files = [];
        $data = (new BlockDataExporter(sys_get_temp_dir()))->exportMedia(new Media(), $files);

        $this->assertNull($data);
        $this->assertSame([], $files);
    }

    public function testExportMediaReturnsNullWhenFileDoesNotExistOnDisk(): void
    {
        $media = (new Media())->setFilename('uploads/missing.jpg');

        $files = [];
        $data = (new BlockDataExporter(sys_get_temp_dir()))->exportMedia($media, $files);

        $this->assertNull($data);
        $this->assertSame([], $files);
    }

    public function testExportMediaRegistersTheFileAndReturnsItsMetadata(): void
    {
        $projectDir = sys_get_temp_dir() . '/block_data_exporter_test_' . bin2hex(random_bytes(4));
        mkdir($projectDir . '/public/uploads', 0777, true);
        $filename = 'uploads/photo.jpg';
        file_put_contents($projectDir . '/public/' . $filename, 'fake-image-bytes');

        $media = (new Media())
            ->setFilename($filename)
            ->setRole('illustration')
            ->setName('rapport-annuel')
            ->setAlt('A photo')
            ->setPosition(0);

        $files = [];
        $data = (new BlockDataExporter($projectDir))->exportMedia($media, $files);

        $this->assertNotNull($data);
        $this->assertSame('illustration', $data['role']);
        $this->assertSame('rapport-annuel', $data['name']);
        $this->assertSame('A photo', $data['alt']);
        $this->assertSame('photo.jpg', $data['originalFilename']);
        $this->assertNull($data['thumbnail']);
        $this->assertCount(1, $files);
        $this->assertSame($projectDir . '/public/' . $filename, array_values($files)[0]);
        $this->assertSame(array_key_first($files), $data['file']);

        unlink($projectDir . '/public/' . $filename);
        rmdir($projectDir . '/public/uploads');
        rmdir($projectDir . '/public');
        rmdir($projectDir);
    }

    public function testExportMediaRegistersAPdfsCompanionWebpThumbnailWhenItExistsOnDisk(): void
    {
        $projectDir = sys_get_temp_dir() . '/block_data_exporter_test_' . bin2hex(random_bytes(4));
        mkdir($projectDir . '/public/uploads', 0777, true);
        $filename = 'uploads/rapport.pdf';
        file_put_contents($projectDir . '/public/' . $filename, 'fake-pdf-bytes');
        file_put_contents($projectDir . '/public/uploads/rapport.webp', 'fake-webp-bytes');

        $media = (new Media())->setFilename($filename);

        $files = [];
        $data = (new BlockDataExporter($projectDir))->exportMedia($media, $files);

        $this->assertNotNull($data);
        $this->assertNotNull($data['thumbnail']);
        $this->assertCount(2, $files);
        $this->assertSame($projectDir . '/public/uploads/rapport.webp', $files[$data['thumbnail']]);

        unlink($projectDir . '/public/' . $filename);
        unlink($projectDir . '/public/uploads/rapport.webp');
        rmdir($projectDir . '/public/uploads');
        rmdir($projectDir . '/public');
        rmdir($projectDir);
    }

    public function testExportMediaLeavesThumbnailNullWhenThePdfHasNoCompanionWebpYet(): void
    {
        $projectDir = sys_get_temp_dir() . '/block_data_exporter_test_' . bin2hex(random_bytes(4));
        mkdir($projectDir . '/public/uploads', 0777, true);
        $filename = 'uploads/rapport.pdf';
        file_put_contents($projectDir . '/public/' . $filename, 'fake-pdf-bytes');

        $media = (new Media())->setFilename($filename);

        $files = [];
        $data = (new BlockDataExporter($projectDir))->exportMedia($media, $files);

        $this->assertNotNull($data);
        $this->assertNull($data['thumbnail']);
        $this->assertCount(1, $files);

        unlink($projectDir . '/public/' . $filename);
        rmdir($projectDir . '/public/uploads');
        rmdir($projectDir . '/public');
        rmdir($projectDir);
    }
}
