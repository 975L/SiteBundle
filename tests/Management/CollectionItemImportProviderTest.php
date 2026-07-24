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
use c975L\SiteBundle\Management\CollectionGroupResolver;
use c975L\SiteBundle\Management\CollectionItemImportProvider;
use c975L\SiteBundle\Repository\CollectionGroupRepository;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

class CollectionItemImportProviderTest extends TestCase
{
    private function createCollectionItemRepository(?CollectionItem $existingItem = null): CollectionItemRepository
    {
        $repository = $this->createStub(CollectionItemRepository::class);
        $repository->method('findOneByCollectionGroupAndSlug')->willReturn($existingItem);

        return $repository;
    }

    // Simulates the referenced collection already existing on this environment - findOneBySlug() resolves it, so it's never (re)created
    private function createCollectionGroupResolver(?CollectionGroup $existingCollectionGroup = null): CollectionGroupResolver
    {
        $repository = $this->createStub(CollectionGroupRepository::class);
        $repository->method('findOneBySlug')->willReturn($existingCollectionGroup);

        return new CollectionGroupResolver($repository, new AsciiSlugger());
    }

    private function createFilesDir(string $entryPath, string $content): string
    {
        $filesDir = sys_get_temp_dir() . '/collection_item_import_test_' . bin2hex(random_bytes(4));
        mkdir(\dirname($filesDir . '/' . $entryPath), 0777, true);
        file_put_contents($filesDir . '/' . $entryPath, $content);

        return $filesDir;
    }

    public function testSupportsImportOnlyMatchesSiteCollectionItemKind(): void
    {
        $provider = new CollectionItemImportProvider(
            $this->createStub(EntityManagerInterface::class),
            $this->createCollectionItemRepository(),
            $this->createCollectionGroupResolver(),
        );

        $this->assertTrue($provider->supportsImport('site_collection_item'));
        $this->assertFalse($provider->supportsImport('site_page'));
    }

    public function testImportCreatesANewCollectionItemFromTheExtractedZipFile(): void
    {
        $filesDir = $this->createFilesDir('files/project-cover.jpg', 'fake-image-bytes');
        $projects = (new CollectionGroup())->setName('Projects')->setSlug('projects');

        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $provider = new CollectionItemImportProvider(
            $em,
            $this->createCollectionItemRepository(),
            $this->createCollectionGroupResolver($projects),
        );

        $result = $provider->import([[
            'collectionGroup' => 'Projects',
            'title' => 'My Project',
            'slug' => 'my-project',
            'description' => 'A description',
            'url' => 'https://example.com',
            'position' => 2,
            'originalFilename' => 'project-cover.jpg',
            'file' => 'files/project-cover.jpg',
        ]], $filesDir);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);
        $this->assertCount(1, $persisted);

        $item = $persisted[0];
        $this->assertSame($projects, $item->getCollectionGroup());
        $this->assertSame('my-project', $item->getSlug());
        $this->assertSame(2, $item->getPosition());

        $file = $item->getFile();
        $this->assertNotNull($file);
        $this->assertSame($filesDir . '/files/project-cover.jpg', $file->getPathname());
        $this->assertSame('fake-image-bytes', file_get_contents($file->getPathname()));

        unlink($file->getPathname());
        rmdir($filesDir . '/files');
        rmdir($filesDir);
    }

    // The referenced collection doesn't exist yet on this environment - created on the fly by name, so importing a fresh site's content never needs a manual "create the collections first" step
    public function testImportCreatesTheReferencedCollectionGroupWhenItDoesNotExistYet(): void
    {
        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $provider = new CollectionItemImportProvider(
            $em,
            $this->createCollectionItemRepository(),
            $this->createCollectionGroupResolver(null),
        );

        $provider->import([[
            'collectionGroup' => 'New Collection',
            'title' => 'First Item',
            'slug' => 'first-item',
            'position' => 0,
        ]]);

        $collectionGroups = array_values(array_filter($persisted, static fn (object $entity): bool => $entity instanceof CollectionGroup));
        $this->assertCount(1, $collectionGroups);
        $this->assertSame('New Collection', $collectionGroups[0]->getName());
        $this->assertSame('new-collection', $collectionGroups[0]->getSlug());
    }

    // Resolving by normalized slug rather than exact name means a name that only differs in casing/punctuation still matches the existing collection instead of creating a duplicate - see CollectionItemImportCommand's own equivalent test, both now share CollectionGroupResolver
    public function testImportMatchesAnExistingCollectionGroupBySlugRegardlessOfNameVariation(): void
    {
        $projects = (new CollectionGroup())->setName('Projects')->setSlug('projects');

        $repository = $this->createStub(CollectionGroupRepository::class);
        $repository->method('findOneBySlug')->willReturnCallback(
            static fn (string $slug): ?CollectionGroup => 'projects' === $slug ? $projects : null
        );

        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $provider = new CollectionItemImportProvider(
            $em,
            $this->createCollectionItemRepository(),
            new CollectionGroupResolver($repository, new AsciiSlugger()),
        );

        $provider->import([[
            'collectionGroup' => 'Projects!',
            'title' => 'First Item',
            'slug' => 'first-item',
            'position' => 0,
        ]]);

        $this->assertCount(0, array_filter($persisted, static fn (object $entity): bool => $entity instanceof CollectionGroup));
    }

    // Several items in the same batch belonging to the same not-yet-existing collection must resolve to the exact same CollectionGroup instance, not one each
    public function testImportOnlyCreatesTheSameMissingCollectionGroupOnce(): void
    {
        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $provider = new CollectionItemImportProvider(
            $em,
            $this->createCollectionItemRepository(),
            $this->createCollectionGroupResolver(null),
        );

        $provider->import([
            ['collectionGroup' => 'New Collection', 'title' => 'First', 'slug' => 'first', 'position' => 0],
            ['collectionGroup' => 'New Collection', 'title' => 'Second', 'slug' => 'second', 'position' => 1],
        ]);

        $collectionGroups = array_filter($persisted, static fn (object $entity): bool => $entity instanceof CollectionGroup);
        $this->assertCount(1, $collectionGroups);

        $items = array_values(array_filter($persisted, static fn (object $entity): bool => $entity instanceof CollectionItem));
        $this->assertSame($items[0]->getCollectionGroup(), $items[1]->getCollectionGroup());
    }

    // Two different names slugifying to the same value within one batch must not both get the plain slug - findOneBySlug() can't see the first one until flush(), only the in-batch $usedSlugs guard catches this
    public function testImportSuffixesTwoDifferentNamesCollidingOnTheSameSlugWithinTheSameBatch(): void
    {
        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $provider = new CollectionItemImportProvider(
            $em,
            $this->createCollectionItemRepository(),
            $this->createCollectionGroupResolver(null),
        );

        $provider->import([
            ['collectionGroup' => 'New York!', 'title' => 'First', 'slug' => 'first', 'position' => 0],
            ['collectionGroup' => 'New York?', 'title' => 'Second', 'slug' => 'second', 'position' => 1],
        ]);

        $collectionGroups = array_values(array_filter($persisted, static fn (object $entity): bool => $entity instanceof CollectionGroup));
        $this->assertCount(2, $collectionGroups);
        $this->assertSame('new-york', $collectionGroups[0]->getSlug());
        $this->assertSame('new-york-2', $collectionGroups[1]->getSlug());
    }

    public function testImportOverwritesAnExistingItemsFile(): void
    {
        $filesDir = $this->createFilesDir('files/project-cover.jpg', 'new-image-bytes');
        $projects = (new CollectionGroup())->setName('Projects')->setSlug('projects');
        $existing = (new CollectionItem())->setCollectionGroup($projects)->setTitle('My Project')->setSlug('my-project')->setPosition(0);

        $provider = new CollectionItemImportProvider(
            $this->createStub(EntityManagerInterface::class),
            $this->createCollectionItemRepository($existing),
            $this->createCollectionGroupResolver($projects),
        );

        $result = $provider->import([[
            'collectionGroup' => 'Projects',
            'title' => 'My Project',
            'slug' => 'my-project',
            'position' => 0,
            'originalFilename' => 'project-cover.jpg',
            'file' => 'files/project-cover.jpg',
        ]], $filesDir);

        $this->assertSame(['created' => 0, 'updated' => 1], $result);

        $file = $existing->getFile();
        $this->assertNotNull($file);
        $this->assertSame('new-image-bytes', file_get_contents($file->getPathname()));

        unlink($file->getPathname());
        rmdir($filesDir . '/files');
        rmdir($filesDir);
    }

    public function testImportCreatesAnItemWithNoFileWhenExportCarriedNone(): void
    {
        $projects = (new CollectionGroup())->setName('Projects')->setSlug('projects');

        $persisted = [];
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $provider = new CollectionItemImportProvider(
            $em,
            $this->createCollectionItemRepository(),
            $this->createCollectionGroupResolver($projects),
        );

        $result = $provider->import([[
            'collectionGroup' => 'Projects',
            'title' => 'No Image',
            'slug' => 'no-image',
            'position' => 0,
        ]]);

        $this->assertSame(['created' => 1, 'updated' => 0], $result);
        $this->assertNull($persisted[0]->getFile());
    }
}
