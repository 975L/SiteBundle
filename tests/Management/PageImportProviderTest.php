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
use c975L\SiteBundle\Management\BlockDataImporter;
use c975L\SiteBundle\Management\PageImportProvider;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\DefaultPagesImporter;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;

class PageImportProviderTest extends TestCase
{
    private function createPageRepository(?Page $existingPage = null): PageRepository
    {
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findOneBy')->willReturn($existingPage);

        return $repository;
    }

    public function testSupportsImportOnlyMatchesSitePageKind(): void
    {
        $em = $this->createStub(EntityManagerInterface::class);
        $provider = new PageImportProvider(
            $em,
            $this->createPageRepository(),
            new BlockDataImporter($em, $this->createStub(DefaultPagesImporter::class)),
        );

        $this->assertTrue($provider->supportsImport('site_page'));
        $this->assertFalse($provider->supportsImport('site_config'));
    }

    public function testImportCreatesANewPageWithItsBlocks(): void
    {
        $persisted = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $em->expects($this->once())->method('flush');

        $defaultPagesImporter = $this->createMock(DefaultPagesImporter::class);
        $defaultPagesImporter->expects($this->once())
            ->method('ensureFormBlockDependenciesExist')
            ->with(['kind' => 'form', 'position' => 0, 'data' => ['name' => 'contact']]);

        $provider = new PageImportProvider($em, $this->createPageRepository(), new BlockDataImporter($em, $defaultPagesImporter));

        $result = $provider->import([[
            'title' => 'Contact',
            'slug' => 'contact',
            'changeFrequency' => 'yearly',
            'priority' => 1,
            'isPublished' => true,
            'blocks' => [
                ['kind' => 'form', 'position' => 0, 'data' => ['name' => 'contact']],
            ],
        ]]);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);

        $page = null;
        foreach ($persisted as $entity) {
            if ($entity instanceof Page) {
                $page = $entity;
            }
        }
        $this->assertInstanceOf(Page::class, $page);
        $this->assertSame('Contact', $page->getTitle());
        $this->assertSame('contact', $page->getSlug());
        $this->assertTrue($page->isPublished());
        $this->assertCount(1, $page->getBlocks());
        $this->assertSame('form', $page->getBlocks()->first()->getKind());
    }

    public function testImportOverwritesAnExistingPageAndReplacesItsBlocks(): void
    {
        $existingBlock = (new Block())->setKind('text')->setPosition(0)->setData(['content' => 'old']);
        $existingPage = (new Page())->setTitle('Old title')->setSlug('about')->setIsPublished(false);
        $existingPage->addBlock($existingBlock);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('flush');

        $provider = new PageImportProvider(
            $em,
            $this->createPageRepository($existingPage),
            new BlockDataImporter($em, $this->createStub(DefaultPagesImporter::class)),
        );

        $result = $provider->import([[
            'title' => 'New title',
            'slug' => 'about',
            'changeFrequency' => null,
            'priority' => null,
            'isPublished' => true,
            'blocks' => [
                ['kind' => 'text', 'position' => 0, 'data' => ['content' => 'new']],
            ],
        ]]);

        $this->assertSame(['created' => 0, 'updated' => 1], $result);
        $this->assertSame('New title', $existingPage->getTitle());
        $this->assertTrue($existingPage->isPublished());
        $this->assertCount(1, $existingPage->getBlocks());
        $this->assertSame('new', $existingPage->getBlocks()->first()->getData()['content']);
    }

    public function testImportRebuildsMediaFromTheExtractedZipFile(): void
    {
        $filesDir = sys_get_temp_dir() . '/page_import_test_' . bin2hex(random_bytes(4));
        mkdir($filesDir . '/files', 0777, true);
        file_put_contents($filesDir . '/files/photo.jpg', 'fake-image-bytes');

        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $provider = new PageImportProvider($em, $this->createPageRepository(), new BlockDataImporter($em, $this->createStub(DefaultPagesImporter::class)));

        $provider->import([[
            'title' => 'About',
            'slug' => 'about',
            'blocks' => [[
                'kind' => 'image',
                'position' => 0,
                'data' => [],
                'medias' => [[
                    'role' => 'illustration',
                    'alt' => 'A photo',
                    'position' => 0,
                    'originalFilename' => 'photo.jpg',
                    'file' => 'files/photo.jpg',
                ]],
            ]],
        ]], $filesDir);

        $media = null;
        foreach ($persisted as $entity) {
            if ($entity instanceof Media) {
                $media = $entity;
            }
        }
        $this->assertInstanceOf(Media::class, $media);
        $this->assertSame('illustration', $media->getRole());
        $this->assertSame('A photo', $media->getAlt());

        $file = $media->getFile();
        $this->assertNotNull($file);
        $this->assertSame($filesDir . '/files/photo.jpg', $file->getPathname());
        $this->assertSame('fake-image-bytes', file_get_contents($file->getPathname()));

        unlink($filesDir . '/files/photo.jpg');
        rmdir($filesDir . '/files');
        rmdir($filesDir);
    }

    public function testImportRebuildsAContainerBlocksNestedSlotsRecursively(): void
    {
        $filesDir = sys_get_temp_dir() . '/page_import_test_' . bin2hex(random_bytes(4));
        mkdir($filesDir . '/files', 0777, true);
        file_put_contents($filesDir . '/files/brochure.pdf', 'fake-pdf-bytes');

        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });
        $provider = new PageImportProvider($em, $this->createPageRepository(), new BlockDataImporter($em, $this->createStub(DefaultPagesImporter::class)));

        $provider->import([[
            'title' => 'About',
            'slug' => 'about',
            'blocks' => [[
                'kind' => 'flex_columns',
                'position' => 0,
                'data' => [],
                'slots' => [[
                    'kind' => 'document_download',
                    'position' => 0,
                    'data' => ['label' => 'Brochure'],
                    'animation' => 'fade-in',
                    'medias' => [[
                        'position' => 0,
                        'originalFilename' => 'brochure.pdf',
                        'file' => 'files/brochure.pdf',
                    ]],
                ]],
            ]],
        ]], $filesDir);

        $page = null;
        foreach ($persisted as $entity) {
            if ($entity instanceof Page) {
                $page = $entity;
            }
        }
        $this->assertInstanceOf(Page::class, $page);
        $container = $page->getBlocks()->first();
        $this->assertSame('flex_columns', $container->getKind());
        $this->assertCount(1, $container->getSlots());

        $slot = $container->getSlots()->first();
        $this->assertSame('document_download', $slot->getKind());
        $this->assertSame('fade-in', $slot->getAnimation());
        $this->assertCount(1, $slot->getMedias());
        $this->assertSame('fake-pdf-bytes', file_get_contents($slot->getMedias()->first()->getFile()->getPathname()));

        unlink($filesDir . '/files/brochure.pdf');
        rmdir($filesDir . '/files');
        rmdir($filesDir);
    }

    public function testImportRebuildsThePagesOwnSummaryAndOgImage(): void
    {
        $filesDir = sys_get_temp_dir() . '/page_import_test_' . bin2hex(random_bytes(4));
        mkdir($filesDir . '/files', 0777, true);
        file_put_contents($filesDir . '/files/og.jpg', 'fake-og-image-bytes');

        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $provider = new PageImportProvider($em, $this->createPageRepository(), new BlockDataImporter($em, $this->createStub(DefaultPagesImporter::class)));

        $provider->import([[
            'title' => 'About',
            'slug' => 'about',
            'summarySocialNetwork' => 'Shared on social networks',
            'ogImage' => [
                'position' => 0,
                'originalFilename' => 'og.jpg',
                'file' => 'files/og.jpg',
            ],
            'blocks' => [],
        ]], $filesDir);

        $page = null;
        foreach ($persisted as $entity) {
            if ($entity instanceof Page) {
                $page = $entity;
            }
        }
        $this->assertInstanceOf(Page::class, $page);
        $this->assertSame('Shared on social networks', $page->getSummarySocialNetwork());
        $this->assertNotNull($page->getOgImage());
        $this->assertSame('fake-og-image-bytes', file_get_contents($page->getOgImage()->getFile()->getPathname()));

        unlink($filesDir . '/files/og.jpg');
        rmdir($filesDir . '/files');
        rmdir($filesDir);
    }

    public function testImportRemovesTheExistingOgImageWhenTheExportCarriesNone(): void
    {
        $existingOgImage = (new Media())->setFilename('uploads/old-og.jpg');
        $existingPage = (new Page())->setTitle('About')->setSlug('about')->setOgImage($existingOgImage);

        $removed = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('remove')->willReturnCallback(static function (object $entity) use (&$removed): void {
            $removed[] = $entity;
        });

        $provider = new PageImportProvider($em, $this->createPageRepository($existingPage), new BlockDataImporter($em, $this->createStub(DefaultPagesImporter::class)));

        $provider->import([[
            'title' => 'About',
            'slug' => 'about',
            'blocks' => [],
        ]]);

        $this->assertSame([$existingOgImage], $removed);
        $this->assertNull($existingPage->getOgImage());
    }
}
