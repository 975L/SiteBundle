<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Service;

use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Repository\CollectionGroupRepository;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use c975L\UiBundle\Contract\CollectionSourceProviderInterface;
use c975L\UiBundle\Model\CollectionItem as CollectionItemModel;
use Vich\UploaderBundle\Templating\Helper\UploaderHelperInterface;

// Exposes every CollectionGroup as its own source, keyed "site.collection.{slug}" - one entry per collection created via CollectionCrudController, so creating a brand new collection there is enough to make it pickable in the "collection" block, no code change needed.
class CollectionItemSourceProvider implements CollectionSourceProviderInterface
{
    // Per-collection memoization, keyed by id: several "collection" blocks on the same page can reference the same collection, each invoking 'count'/'items' independently - this keeps that to one query per collection per request instead of one per block
    private array $counts = [];
    private array $items = [];

    public function __construct(
        private readonly CollectionGroupRepository $collectionGroupRepository,
        private readonly CollectionItemRepository $collectionItemRepository,
        private readonly UploaderHelperInterface $uploaderHelper,
    ) {
    }

    public function getSources(): array
    {
        $sources = [];
        foreach ($this->collectionGroupRepository->findBy([], ['name' => 'ASC']) as $collectionGroup) {
            $sources['site.collection.' . $collectionGroup->getSlug()] = [
                'label' => (string) $collectionGroup->getName(),
                'count' => fn (): int => $this->countByCollectionGroup($collectionGroup),
                'items' => fn (?int $limit): array => $this->itemsByCollectionGroup($collectionGroup, $limit),
                'detail' => fn (string $slug): ?array => $this->detail($collectionGroup, $slug),
            ];
        }

        return $sources;
    }

    private function countByCollectionGroup(CollectionGroup $collectionGroup): int
    {
        return $this->counts[$collectionGroup->getId()] ??= $this->collectionItemRepository->countByCollectionGroup($collectionGroup);
    }

    // Caches the unlimited list per collection and slices it in-memory for any smaller $limit, so two blocks referencing the same collection (e.g. a teaser and a full listing) share a single query
    private function itemsByCollectionGroup(CollectionGroup $collectionGroup, ?int $limit): array
    {
        $this->items[$collectionGroup->getId()] ??= array_map(
            fn (CollectionItem $item): CollectionItemModel => $this->toCollectionItemModel($item),
            $this->collectionItemRepository->findByCollectionGroup($collectionGroup)
        );

        return null !== $limit
            ? array_slice($this->items[$collectionGroup->getId()], 0, $limit)
            : $this->items[$collectionGroup->getId()];
    }

    private function toCollectionItemModel(CollectionItem $item): CollectionItemModel
    {
        return new CollectionItemModel(
            title: (string) $item->getTitle(),
            description: $item->getDescription(),
            imageUrl: $this->uploaderHelper->asset($item, 'file'),
            url: $item->getUrl(),
            slug: $item->getSlug(),
        );
    }

    // Backs this source's "detail" capability (see CollectionSourceProviderInterface) - resolves one item by its own slug, scoped to this collection so the same slug can be reused across different collections
    private function detail(CollectionGroup $collectionGroup, string $slug): ?array
    {
        $item = $this->collectionItemRepository->findOneByCollectionGroupAndSlug($collectionGroup, $slug);
        if (null === $item) {
            return null;
        }

        $model = $this->toCollectionItemModel($item);

        return [
            'title' => $model->title,
            'description' => $model->description,
            'imageUrl' => $model->imageUrl,
            'url' => $model->url,
        ];
    }
}
