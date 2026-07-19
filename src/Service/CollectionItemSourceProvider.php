<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Service;

use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use c975L\UiBundle\Contract\CollectionSourceProviderInterface;
use c975L\UiBundle\Model\CollectionItem as CollectionItemModel;
use Vich\UploaderBundle\Templating\Helper\UploaderHelperInterface;

// Exposes every distinct CollectionItem "group" (e.g. "projects") as its own source, keyed "site.collection.{group}" - one item per group found in the table, so adding a brand new group via the CRUD is enough to make it pickable in the "collection" block, no code change needed.
class CollectionItemSourceProvider implements CollectionSourceProviderInterface
{
    // Per-group memoization: several "collection" blocks on the same page can reference the same group, each invoking 'count'/'items' independently - this keeps that to one query per group per request instead of one per block
    private array $counts = [];
    private array $items = [];

    public function __construct(
        private readonly CollectionItemRepository $collectionItemRepository,
        private readonly UploaderHelperInterface $uploaderHelper,
    ) {
    }

    public function getSources(): array
    {
        $sources = [];
        foreach ($this->collectionItemRepository->findDistinctGroups() as $group) {
            $sources['site.collection.' . $group] = [
                'label' => $group,
                'count' => fn (): int => $this->countByGroup($group),
                'items' => fn (?int $limit): array => $this->itemsByGroup($group, $limit),
                'detail' => fn (string $slug): ?array => $this->detail($group, $slug),
            ];
        }

        return $sources;
    }

    private function countByGroup(string $group): int
    {
        return $this->counts[$group] ??= $this->collectionItemRepository->countByGroup($group);
    }

    // Caches the unlimited list per group and slices it in-memory for any smaller $limit, so two blocks referencing the same group (e.g. a teaser and a full listing) share a single query
    private function itemsByGroup(string $group, ?int $limit): array
    {
        $this->items[$group] ??= array_map(
            fn (CollectionItem $item): CollectionItemModel => $this->toCollectionItemModel($item),
            $this->collectionItemRepository->findByGroup($group)
        );

        return null !== $limit ? array_slice($this->items[$group], 0, $limit) : $this->items[$group];
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

    // Backs this source's "detail" capability (see CollectionSourceProviderInterface) - resolves one item by its own slug, scoped to this group so the same slug can be reused across different groups
    private function detail(string $group, string $slug): ?array
    {
        $item = $this->collectionItemRepository->findOneByGroupAndSlug($group, $slug);
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
