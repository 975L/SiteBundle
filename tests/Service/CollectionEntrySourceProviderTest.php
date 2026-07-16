<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Entity\CollectionEntry;
use c975L\SiteBundle\Repository\CollectionEntryRepository;
use c975L\SiteBundle\Service\CollectionEntrySourceProvider;
use PHPUnit\Framework\TestCase;
use Vich\UploaderBundle\Templating\Helper\UploaderHelperInterface;

class CollectionEntrySourceProviderTest extends TestCase
{
    public function testGetSourcesExposesOneSourcePerDistinctGroup(): void
    {
        $repository = $this->createStub(CollectionEntryRepository::class);
        $repository->method('findDistinctGroups')->willReturn(['projects', 'books']);

        $provider = new CollectionEntrySourceProvider($repository, $this->createStub(UploaderHelperInterface::class));

        $this->assertSame(
            ['site.collection.projects', 'site.collection.books'],
            array_keys($provider->getSources())
        );
    }

    public function testCountDelegatesToCountByGroup(): void
    {
        $repository = $this->createStub(CollectionEntryRepository::class);
        $repository->method('findDistinctGroups')->willReturn(['projects']);
        $repository->method('countByGroup')->willReturn(12);

        $sources = (new CollectionEntrySourceProvider($repository, $this->createStub(UploaderHelperInterface::class)))->getSources();

        $this->assertSame(12, ($sources['site.collection.projects']['count'])());
    }

    // The resolved image URL comes from Vich's own asset helper (same as vich_uploader_asset() in Twig),
    // not a raw filename - so it works whatever the mapping/storage resolves it to
    public function testItemsMapsEachEntryToACollectionItem(): void
    {
        $entry = (new CollectionEntry())
            ->setGroup('projects')
            ->setTitle('Papa Câlin')
            ->setDescription("Des histoires inventées à partir des idées d'enfants.")
            ->setUrl('https://papa-calin.com');

        $repository = $this->createStub(CollectionEntryRepository::class);
        $repository->method('findDistinctGroups')->willReturn(['projects']);
        $repository->method('findByGroup')->willReturn([$entry]);

        $uploaderHelper = $this->createStub(UploaderHelperInterface::class);
        $uploaderHelper->method('asset')->willReturn('/medias/site/collection-projects-42-abc.webp');

        $sources = (new CollectionEntrySourceProvider($repository, $uploaderHelper))->getSources();
        $items = ($sources['site.collection.projects']['items'])(6);

        $this->assertCount(1, $items);
        $this->assertSame('Papa Câlin', $items[0]->title);
        $this->assertSame("Des histoires inventées à partir des idées d'enfants.", $items[0]->description);
        $this->assertSame('/medias/site/collection-projects-42-abc.webp', $items[0]->imageUrl);
        $this->assertSame('https://papa-calin.com', $items[0]->url);
    }

    // The unlimited list is fetched once and sliced in-memory for a given $limit, rather than passing
    // it down to findByGroup() - lets a second call for the same group (see below) reuse the same query
    public function testItemsSlicesToTheGivenLimitWithoutRequeryingFindByGroup(): void
    {
        $entries = [
            (new CollectionEntry())->setGroup('projects')->setTitle('A'),
            (new CollectionEntry())->setGroup('projects')->setTitle('B'),
            (new CollectionEntry())->setGroup('projects')->setTitle('C'),
        ];

        $repository = $this->createMock(CollectionEntryRepository::class);
        $repository->method('findDistinctGroups')->willReturn(['projects']);
        $repository->expects($this->once())->method('findByGroup')->with('projects')->willReturn($entries);

        $sources = (new CollectionEntrySourceProvider($repository, $this->createStub(UploaderHelperInterface::class)))->getSources();
        $items = ($sources['site.collection.projects']['items'])(2);

        $this->assertCount(2, $items);
        $this->assertSame('A', $items[0]->title);
        $this->assertSame('B', $items[1]->title);
    }

    // Two "collection" blocks referencing the same group (e.g. a teaser and a full listing) must not
    // each trigger their own query - count()/items() are memoized per group for the provider's lifetime
    public function testCountAndItemsAreMemoizedPerGroup(): void
    {
        $repository = $this->createMock(CollectionEntryRepository::class);
        $repository->method('findDistinctGroups')->willReturn(['projects']);
        $repository->expects($this->once())->method('countByGroup')->with('projects')->willReturn(3);
        $repository->expects($this->once())->method('findByGroup')->with('projects')->willReturn([]);

        $sources = (new CollectionEntrySourceProvider($repository, $this->createStub(UploaderHelperInterface::class)))->getSources();

        ($sources['site.collection.projects']['count'])();
        ($sources['site.collection.projects']['count'])();
        ($sources['site.collection.projects']['items'])(3);
        ($sources['site.collection.projects']['items'])(null);
    }
}
