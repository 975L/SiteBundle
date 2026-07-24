<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Repository\CollectionGroupRepository;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use c975L\SiteBundle\Service\CollectionItemSourceProvider;
use PHPUnit\Framework\TestCase;
use Vich\UploaderBundle\Templating\Helper\UploaderHelperInterface;

class CollectionItemSourceProviderTest extends TestCase
{
    private function withId(CollectionGroup $collectionGroup, int $id): CollectionGroup
    {
        (new \ReflectionProperty(CollectionGroup::class, 'id'))->setValue($collectionGroup, $id);

        return $collectionGroup;
    }

    private function collectionGroupRepository(array $collectionGroups): CollectionGroupRepository
    {
        $repository = $this->createStub(CollectionGroupRepository::class);
        $repository->method('findBy')->willReturn($collectionGroups);

        return $repository;
    }

    public function testGetSourcesExposesOneSourcePerCollectionGroup(): void
    {
        $projects = $this->withId((new CollectionGroup())->setName('Projects')->setSlug('projects'), 1);
        $books = $this->withId((new CollectionGroup())->setName('Books')->setSlug('books'), 2);

        $provider = new CollectionItemSourceProvider(
            $this->collectionGroupRepository([$projects, $books]),
            $this->createStub(CollectionItemRepository::class),
            $this->createStub(UploaderHelperInterface::class),
        );

        $this->assertSame(
            ['site.collection.projects', 'site.collection.books'],
            array_keys($provider->getSources())
        );
    }

    public function testCountDelegatesToCountByCollectionGroup(): void
    {
        $projects = $this->withId((new CollectionGroup())->setName('Projects')->setSlug('projects'), 1);

        $collectionItemRepository = $this->createStub(CollectionItemRepository::class);
        $collectionItemRepository->method('countByCollectionGroup')->willReturn(12);

        $sources = (new CollectionItemSourceProvider(
            $this->collectionGroupRepository([$projects]),
            $collectionItemRepository,
            $this->createStub(UploaderHelperInterface::class),
        ))->getSources();

        $this->assertSame(12, ($sources['site.collection.projects']['count'])());
    }

    // The resolved image URL comes from Vich's own asset helper (same as vich_uploader_asset() in Twig), not a raw filename - so it works whatever the mapping/storage resolves it to
    public function testItemsMapsEachItemToACollectionItemModel(): void
    {
        $projects = $this->withId((new CollectionGroup())->setName('Projects')->setSlug('projects'), 1);

        $item = (new CollectionItem())
            ->setCollectionGroup($projects)
            ->setTitle('Papa Câlin')
            ->setSlug('papa-calin')
            ->setDescription("Des histoires inventées à partir des idées d'enfants.")
            ->setUrl('https://papa-calin.com');

        $collectionItemRepository = $this->createStub(CollectionItemRepository::class);
        $collectionItemRepository->method('findByCollectionGroup')->willReturn([$item]);

        $uploaderHelper = $this->createStub(UploaderHelperInterface::class);
        $uploaderHelper->method('asset')->willReturn('/medias/site/collection-projects-42-abc.webp');

        $sources = (new CollectionItemSourceProvider(
            $this->collectionGroupRepository([$projects]),
            $collectionItemRepository,
            $uploaderHelper,
        ))->getSources();
        $items = ($sources['site.collection.projects']['items'])(6);

        $this->assertCount(1, $items);
        $this->assertSame('Papa Câlin', $items[0]->title);
        $this->assertSame('papa-calin', $items[0]->slug);
        $this->assertSame("Des histoires inventées à partir des idées d'enfants.", $items[0]->description);
        $this->assertSame('/medias/site/collection-projects-42-abc.webp', $items[0]->imageUrl);
        $this->assertSame('https://papa-calin.com', $items[0]->url);
    }

    // The "collection" block's title-link feature (see UiBundle's CollectionExtension) relies on this "detail" callable resolving the same slug items() exposed on the CollectionItem model
    public function testDetailResolvesAnItemScopedToItsOwnCollectionGroup(): void
    {
        $projects = $this->withId((new CollectionGroup())->setName('Projects')->setSlug('projects'), 1);

        $item = (new CollectionItem())
            ->setCollectionGroup($projects)
            ->setTitle('Papa Câlin')
            ->setSlug('papa-calin')
            ->setDescription("Des histoires inventées à partir des idées d'enfants.")
            ->setUrl('https://papa-calin.com');

        $collectionItemRepository = $this->createMock(CollectionItemRepository::class);
        $collectionItemRepository->expects($this->once())
            ->method('findOneByCollectionGroupAndSlug')
            ->with($projects, 'papa-calin')
            ->willReturn($item);

        $uploaderHelper = $this->createStub(UploaderHelperInterface::class);
        $uploaderHelper->method('asset')->willReturn('/medias/site/collection-projects-42-abc.webp');

        $sources = (new CollectionItemSourceProvider(
            $this->collectionGroupRepository([$projects]),
            $collectionItemRepository,
            $uploaderHelper,
        ))->getSources();
        $detail = ($sources['site.collection.projects']['detail'])('papa-calin');

        $this->assertSame([
            'title'       => 'Papa Câlin',
            'description' => "Des histoires inventées à partir des idées d'enfants.",
            'imageUrl'    => '/medias/site/collection-projects-42-abc.webp',
            'url'         => 'https://papa-calin.com',
        ], $detail);
    }

    // By convention, an unresolved slug falls through to null so the caller (PageController) can 404
    public function testDetailReturnsNullWhenSlugDoesNotResolve(): void
    {
        $projects = $this->withId((new CollectionGroup())->setName('Projects')->setSlug('projects'), 1);

        $collectionItemRepository = $this->createStub(CollectionItemRepository::class);
        $collectionItemRepository->method('findOneByCollectionGroupAndSlug')->willReturn(null);

        $sources = (new CollectionItemSourceProvider(
            $this->collectionGroupRepository([$projects]),
            $collectionItemRepository,
            $this->createStub(UploaderHelperInterface::class),
        ))->getSources();

        $this->assertNull(($sources['site.collection.projects']['detail'])('unknown-slug'));
    }

    // The unlimited list is fetched once and sliced in-memory for a given $limit, rather than passing it down to findByCollectionGroup() - lets a second call for the same collection (see below) reuse the same query
    public function testItemsSlicesToTheGivenLimitWithoutRequeryingFindByCollectionGroup(): void
    {
        $projects = $this->withId((new CollectionGroup())->setName('Projects')->setSlug('projects'), 1);

        $items = [
            (new CollectionItem())->setCollectionGroup($projects)->setTitle('A'),
            (new CollectionItem())->setCollectionGroup($projects)->setTitle('B'),
            (new CollectionItem())->setCollectionGroup($projects)->setTitle('C'),
        ];

        $collectionItemRepository = $this->createMock(CollectionItemRepository::class);
        $collectionItemRepository->expects($this->once())->method('findByCollectionGroup')->with($projects)->willReturn($items);

        $sources = (new CollectionItemSourceProvider(
            $this->collectionGroupRepository([$projects]),
            $collectionItemRepository,
            $this->createStub(UploaderHelperInterface::class),
        ))->getSources();
        $items = ($sources['site.collection.projects']['items'])(2);

        $this->assertCount(2, $items);
        $this->assertSame('A', $items[0]->title);
        $this->assertSame('B', $items[1]->title);
    }

    // Two "collection" blocks referencing the same collection (e.g. a teaser and a full listing) must not each trigger their own query - count()/items() are memoized per collection for the provider's lifetime
    public function testCountAndItemsAreMemoizedPerCollectionGroup(): void
    {
        $projects = $this->withId((new CollectionGroup())->setName('Projects')->setSlug('projects'), 1);

        $collectionItemRepository = $this->createMock(CollectionItemRepository::class);
        $collectionItemRepository->expects($this->once())->method('countByCollectionGroup')->with($projects)->willReturn(3);
        $collectionItemRepository->expects($this->once())->method('findByCollectionGroup')->with($projects)->willReturn([]);

        $sources = (new CollectionItemSourceProvider(
            $this->collectionGroupRepository([$projects]),
            $collectionItemRepository,
            $this->createStub(UploaderHelperInterface::class),
        ))->getSources();

        ($sources['site.collection.projects']['count'])();
        ($sources['site.collection.projects']['count'])();
        ($sources['site.collection.projects']['items'])(3);
        ($sources['site.collection.projects']['items'])(null);
    }
}
