<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\BlockDataExporter;
use c975L\SiteBundle\Management\PageExportProvider;
use c975L\SiteBundle\Management\PageImportProvider;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use PHPUnit\Framework\TestCase;

class PageExportProviderTest extends TestCase
{
    public function testGetKindMatchesPageImportProvider(): void
    {
        $provider = new PageExportProvider($this->createStub(PageRepository::class), new BlockDataExporter(sys_get_temp_dir()));

        $this->assertSame(PageImportProvider::KIND, $provider->getKind());
    }

    public function testExportAllSerializesEveryNonDeletedPageFromTheRepository(): void
    {
        $block = (new Block())->setKind('text')->setPosition(0)->setData(['content' => 'hello']);
        $page = (new Page())->setTitle('About')->setSlug('about')->setIsPublished(true);
        $page->addBlock($block);

        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->expects($this->once())
            ->method('findBy')
            ->with(['isDeleted' => false])
            ->willReturn([$page]);

        $data = (new PageExportProvider($pageRepository, new BlockDataExporter(sys_get_temp_dir())))->exportAll();

        $this->assertSame([[
            'title' => 'About',
            'slug' => 'about',
            'changeFrequency' => null,
            'priority' => null,
            'isPublished' => true,
            'summarySocialNetwork' => null,
            'ogImage' => null,
            'blocks' => [[
                'kind' => 'text',
                'position' => 0,
                'data' => ['content' => 'hello'],
                'animation' => null,
                'medias' => [],
                'slots' => [],
            ]],
        ]], $data['items']);
        $this->assertSame([], $data['files']);
    }

    public function testSerializeExportsAPagesOwnSummaryAndOgImage(): void
    {
        $projectDir = sys_get_temp_dir() . '/page_export_provider_test_' . bin2hex(random_bytes(4));
        mkdir($projectDir . '/public/uploads', 0777, true);
        $filename = 'uploads/og.jpg';
        file_put_contents($projectDir . '/public/' . $filename, 'fake-og-image-bytes');

        $ogImage = (new Media())->setFilename($filename)->setPosition(0);
        $page = (new Page())->setTitle('About')->setSlug('about')->setIsPublished(true);
        $page->setSummarySocialNetwork('Shared on social networks');
        $page->setOgImage($ogImage);

        $data = (new PageExportProvider($this->createStub(PageRepository::class), new BlockDataExporter($projectDir)))->serialize([$page]);

        $this->assertSame('Shared on social networks', $data['items'][0]['summarySocialNetwork']);
        $this->assertSame(basename($filename), $data['items'][0]['ogImage']['originalFilename']);
        $this->assertCount(1, $data['files']);

        unlink($projectDir . '/public/' . $filename);
        rmdir($projectDir . '/public/uploads');
        rmdir($projectDir . '/public');
        rmdir($projectDir);
    }

    public function testSerializeExportsAContainerBlocksNestedSlotsRecursively(): void
    {
        $projectDir = sys_get_temp_dir() . '/page_export_provider_test_' . bin2hex(random_bytes(4));
        mkdir($projectDir . '/public/uploads', 0777, true);
        $filename = 'uploads/brochure.pdf';
        file_put_contents($projectDir . '/public/' . $filename, 'fake-pdf-bytes');

        $media = (new Media())->setFilename($filename)->setPosition(0);
        $slot = (new Block())->setKind('document_download')->setPosition(0)->setData(['label' => 'Brochure'])->setAnimation('fade-in');
        $slot->addMedia($media);
        $container = (new Block())->setKind('flex_columns')->setPosition(0)->setData([]);
        $container->addSlot($slot);
        $page = (new Page())->setTitle('About')->setSlug('about')->setIsPublished(true);
        $page->addBlock($container);

        $data = (new PageExportProvider($this->createStub(PageRepository::class), new BlockDataExporter($projectDir)))->serialize([$page]);

        $slots = $data['items'][0]['blocks'][0]['slots'];
        $this->assertCount(1, $slots);
        $this->assertSame('document_download', $slots[0]['kind']);
        $this->assertSame(['label' => 'Brochure'], $slots[0]['data']);
        $this->assertSame('fade-in', $slots[0]['animation']);
        $this->assertSame([], $slots[0]['slots']);
        $this->assertCount(1, $slots[0]['medias']);
        $this->assertSame(basename($filename), $slots[0]['medias'][0]['originalFilename']);
        $this->assertCount(1, $data['files']);

        unlink($projectDir . '/public/' . $filename);
        rmdir($projectDir . '/public/uploads');
        rmdir($projectDir . '/public');
        rmdir($projectDir);
    }

    public function testSerializeRegistersMediaFilesAlongsideTheirMetadata(): void
    {
        $projectDir = sys_get_temp_dir() . '/page_export_provider_test_' . bin2hex(random_bytes(4));
        mkdir($projectDir . '/public/uploads', 0777, true);
        $filename = 'uploads/photo.jpg';
        file_put_contents($projectDir . '/public/' . $filename, 'fake-image-bytes');

        $media = (new Media())->setFilename($filename)->setRole('illustration')->setAlt('A photo')->setPosition(0);
        $block = (new Block())->setKind('image')->setPosition(0)->setData([]);
        $block->addMedia($media);
        $page = (new Page())->setTitle('About')->setSlug('about')->setIsPublished(true);
        $page->addBlock($block);

        $data = (new PageExportProvider($this->createStub(PageRepository::class), new BlockDataExporter($projectDir)))->serialize([$page]);

        $medias = $data['items'][0]['blocks'][0]['medias'];
        $this->assertCount(1, $medias);
        $this->assertSame('illustration', $medias[0]['role']);
        $this->assertSame('A photo', $medias[0]['alt']);
        $this->assertSame(basename($filename), $medias[0]['originalFilename']);
        $this->assertCount(1, $data['files']);
        $this->assertSame($projectDir . '/public/' . $filename, array_values($data['files'])[0]);

        unlink($projectDir . '/public/' . $filename);
        rmdir($projectDir . '/public/uploads');
        rmdir($projectDir . '/public');
        rmdir($projectDir);
    }
}
