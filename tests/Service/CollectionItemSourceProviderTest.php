<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use c975L\SiteBundle\Service\CollectionItemSourceProvider;
use PHPUnit\Framework\TestCase;
use Vich\UploaderBundle\Templating\Helper\UploaderHelperInterface;

class CollectionItemSourceProviderTest extends TestCase
{
    public function testGetSourcesExposesOneSourcePerDistinctGroup(): void
    {
        $repository = $this->createStub(CollectionItemRepository::class);
        $repository->method('findDistinctGroups')->willReturn(['projects', 'books']);

        $provider = new CollectionItemSourceProvider($repository, $this->createStub(UploaderHelperInterface::class));

        $this->assertSame(
            ['site.collection.projects', 'site.collection.books'],
            array_keys($provider->getSources())
        );
    }

    public function testCountDelegatesToCountByGroup(): void
    {
        $repository = $this->createStub(CollectionItemRepository::class);
        $repository->method('findDistinctGroups')->willReturn(['projects']);
        $repository->method('countByGroup')->willReturn(12);

        $sources = (new CollectionItemSourceProvider($repository, $this->createStub(UploaderHelperInterface::class)))->getSources();

        $this->assertSame(12, ($sources['site.collection.projects']['count'])());
    }

    // The resolved image URL comes from Vich's own asset helper (same as vich_uploader_asset() in Twig), not a raw filename - so it works whatever the mapping/storage resolves it to
    public function testItemsMapsEachItemToACollectionItemModel(): void
    {
        $item = (new CollectionItem())
            ->setGroup('projects')
            ->setTitle('Papa Câlin')
            ->setSlug('papa-calin')
            ->setDescription("Des histoires inventées à partir des idées d'enfants.")
            ->setUrl('https://papa-calin.com');

        $repository = $this->createStub(CollectionItemRepository::class);
        $repository->method('findDistinctGroups')->willReturn(['projects']);
        $repository->method('findByGroup')->willReturn([$item]);

        $uploaderHelper = $this->createStub(UploaderHelperInterface::class);
        $uploaderHelper->method('asset')->willReturn('/medias/site/collection-projects-42-abc.webp');

        $sources = (new CollectionItemSourceProvider($repository, $uploaderHelper))->getSources();
        $items = ($sources['site.collection.projects']['items'])(6);

        $this->assertCount(1, $items);
        $this->assertSame('Papa Câlin', $items[0]->title);
        $this->assertSame('papa-calin', $items[0]->slug);
        $this->assertSame("Des histoires inventées à partir des idées d'enfants.", $items[0]->description);
        $this->assertSame('/medias/site/collection-projects-42-abc.webp', $items[0]->imageUrl);
        $this->assertSame('https://papa-calin.com', $items[0]->url);
    }

    // The "collection" block's title-link feature (see UiBundle's CollectionExtension) relies on this "detail" callable resolving the same slug items() exposed on the CollectionItem model
    public function testDetailResolvesAnItemScopedToItsOwnGroup(): void
    {
        $item = (new CollectionItem())
            ->setGroup('projects')
            ->setTitle('Papa Câlin')
            ->setSlug('papa-calin')
            ->setDescription("Des histoires inventées à partir des idées d'enfants.")
            ->setUrl('https://papa-calin.com');

        $repository = $this->createMock(CollectionItemRepository::class);
        $repository->method('findDistinctGroups')->willReturn(['projects']);
        $repository->expects($this->once())->method('findOneByGroupAndSlug')->with('projects', 'papa-calin')->willReturn($item);

        $uploaderHelper = $this->createStub(UploaderHelperInterface::class);
        $uploaderHelper->method('asset')->willReturn('/medias/site/collection-projects-42-abc.webp');

        $sources = (new CollectionItemSourceProvider($repository, $uploaderHelper))->getSources();
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
        $repository = $this->createStub(CollectionItemRepository::class);
        $repository->method('findDistinctGroups')->willReturn(['projects']);
        $repository->method('findOneByGroupAndSlug')->willReturn(null);

        $sources = (new CollectionItemSourceProvider($repository, $this->createStub(UploaderHelperInterface::class)))->getSources();

        $this->assertNull(($sources['site.collection.projects']['detail'])('unknown-slug'));
    }

    // The unlimited list is fetched once and sliced in-memory for a given $limit, rather than passing it down to findByGroup() - lets a second call for the same group (see below) reuse the same query
    public function testItemsSlicesToTheGivenLimitWithoutRequeryingFindByGroup(): void
    {
        $items = [
            (new CollectionItem())->setGroup('projects')->setTitle('A'),
            (new CollectionItem())->setGroup('projects')->setTitle('B'),
            (new CollectionItem())->setGroup('projects')->setTitle('C'),
        ];

        $repository = $this->createMock(CollectionItemRepository::class);
        $repository->method('findDistinctGroups')->willReturn(['projects']);
        $repository->expects($this->once())->method('findByGroup')->with('projects')->willReturn($items);

        $sources = (new CollectionItemSourceProvider($repository, $this->createStub(UploaderHelperInterface::class)))->getSources();
        $items = ($sources['site.collection.projects']['items'])(2);

        $this->assertCount(2, $items);
        $this->assertSame('A', $items[0]->title);
        $this->assertSame('B', $items[1]->title);
    }

    // Two "collection" blocks referencing the same group (e.g. a teaser and a full listing) must not each trigger their own query - count()/items() are memoized per group for the provider's lifetime
    public function testCountAndItemsAreMemoizedPerGroup(): void
    {
        $repository = $this->createMock(CollectionItemRepository::class);
        $repository->method('findDistinctGroups')->willReturn(['projects']);
        $repository->expects($this->once())->method('countByGroup')->with('projects')->willReturn(3);
        $repository->expects($this->once())->method('findByGroup')->with('projects')->willReturn([]);

        $sources = (new CollectionItemSourceProvider($repository, $this->createStub(UploaderHelperInterface::class)))->getSources();

        ($sources['site.collection.projects']['count'])();
        ($sources['site.collection.projects']['count'])();
        ($sources['site.collection.projects']['items'])(3);
        ($sources['site.collection.projects']['items'])(null);
    }
}
