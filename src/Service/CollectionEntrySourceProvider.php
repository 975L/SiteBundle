<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Service;

use c975L\SiteBundle\Entity\CollectionEntry;
use c975L\SiteBundle\Repository\CollectionEntryRepository;
use c975L\UiBundle\Contract\CollectionSourceProviderInterface;
use c975L\UiBundle\Model\CollectionItem;
use Vich\UploaderBundle\Templating\Helper\UploaderHelperInterface;

// Exposes every distinct CollectionEntry "group" (e.g. "projects") as its own source, keyed
// "site.collection.{group}" - one entry per group found in the table, so adding a brand new group
// via the CRUD is enough to make it pickable in the "collection" block, no code change needed.
class CollectionEntrySourceProvider implements CollectionSourceProviderInterface
{
    // Per-group memoization: several "collection" blocks on the same page can reference the same
    // group, each invoking 'count'/'items' independently - this keeps that to one query per group
    // per request instead of one per block
    private array $counts = [];
    private array $items = [];

    public function __construct(
        private readonly CollectionEntryRepository $collectionEntryRepository,
        private readonly UploaderHelperInterface $uploaderHelper,
    ) {
    }

    public function getSources(): array
    {
        $sources = [];
        foreach ($this->collectionEntryRepository->findDistinctGroups() as $group) {
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
        return $this->counts[$group] ??= $this->collectionEntryRepository->countByGroup($group);
    }

    // Caches the unlimited list per group and slices it in-memory for any smaller $limit, so two
    // blocks referencing the same group (e.g. a teaser and a full listing) share a single query
    private function itemsByGroup(string $group, ?int $limit): array
    {
        $this->items[$group] ??= array_map(
            fn (CollectionEntry $entry): CollectionItem => $this->toCollectionItem($entry),
            $this->collectionEntryRepository->findByGroup($group)
        );

        return null !== $limit ? array_slice($this->items[$group], 0, $limit) : $this->items[$group];
    }

    private function toCollectionItem(CollectionEntry $entry): CollectionItem
    {
        return new CollectionItem(
            title: (string) $entry->getTitle(),
            description: $entry->getDescription(),
            imageUrl: $this->uploaderHelper->asset($entry, 'file'),
            url: $entry->getUrl(),
            slug: $entry->getSlug(),
        );
    }

    // Backs this source's "detail" capability (see CollectionSourceProviderInterface) - resolves one
    // entry by its own slug, scoped to this group so the same slug can be reused across different groups
    private function detail(string $group, string $slug): ?array
    {
        $entry = $this->collectionEntryRepository->findOneByGroupAndSlug($group, $slug);
        if (null === $entry) {
            return null;
        }

        return [
            'title' => $entry->getTitle(),
            'description' => $entry->getDescription(),
            'imageUrl' => $this->uploaderHelper->asset($entry, 'file'),
            'url' => $entry->getUrl(),
        ];
    }
}
