<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Mcp;

use c975L\ConfigBundle\Mcp\McpTool;
use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Repository\CollectionGroupRepository;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use c975L\SiteBundle\Service\CollectionItemSlugs;
use Doctrine\ORM\EntityManagerInterface;
use Mcp\Server\Exception\ToolCallException;
use Symfony\Component\Validator\Validator\ValidatorInterface;

// The collections on the site's MCP endpoint, as on their screens (see CollectionCrudController, CollectionItemCrudController): listed, read with their items in order, an item added and its texts written in the language the site is written in - images, order, collections themselves and deletions left to the back office
class SiteCollectionTools
{
    // Items a collection is read with at most, the page size of its back-office screen
    public const int ITEMS_LIMIT = 100;

    public function __construct(
        private readonly CollectionGroupRepository $collectionGroupRepository,
        private readonly CollectionItemRepository $collectionItemRepository,
        private readonly CollectionItemSlugs $collectionItemSlugs,
        private readonly EntityManagerInterface $entityManager,
        private readonly ValidatorInterface $validator,
        private readonly SiteMcpAccess $access,
    ) {
    }

    // The tools of the collections, under the site key of SiteMcpProvider
    /** @return iterable<McpTool> */
    public function getMcpTools(): iterable
    {
        yield new McpTool('site_collection_list', 'Collections', 'The collections of the site, by name, with how many items each holds.', $this->list(...));
        yield new McpTool('site_collection_get', 'Collection', 'A collection with its items in their order, 100 at most: title, slug, description and link of each, in the language the site is written in.', $this->get(...));
        yield new McpTool('site_collection_item_create', 'Add a collection item', 'Adds an item at the end of a collection, its slug made from its title, in the language the site is written in. Collection items have no draft state: the item shows at once wherever its collection is shown on the site. Its image is added from the back office.', $this->create(...), true);
        yield new McpTool('site_collection_item_edit', 'Write a collection item', 'Changes only the title, description or link given of an item, in the language the site is written in, its other languages left to the back office. The change shows on the site at once; the item keeps its slug and its address.', $this->edit(...), true);
    }

    // Every collection by name, with how many items it holds
    /** @return list<array{id: ?int, name: ?string, slug: ?string, items: int}> */
    public function list(): array
    {
        $this->access->guard('collections');
        $counts = $this->collectionItemRepository->countByCollectionGroups();

        return array_map(
            static fn (CollectionGroup $group): array => [...self::collection($group), 'items' => $counts[(int) $group->getId()] ?? 0],
            $this->collectionGroupRepository->findBy([], ['name' => 'ASC']),
        );
    }

    // A collection with its items in their order
    /**
     * @param int $collection id of the collection
     *
     * @return array{id: ?int, name: ?string, slug: ?string, items: list<array{id: ?int, title: ?string, slug: ?string, description: ?string, url: ?string, position: int, image: bool}>}
     */
    public function get(int $collection): array
    {
        $this->access->guard('collections');
        $found = $this->foundCollection($collection);

        return [
            ...self::collection($found),
            'items' => array_map(self::item(...), $this->collectionItemRepository->findByCollectionGroup($found, self::ITEMS_LIMIT)),
        ];
    }

    // An item added at the end of a collection as its screen adds one, its slug made from its title and kept unique in the collection, refused as the screen's form would refuse it
    /**
     * @param int     $collection  id of the collection
     * @param string  $title       title of the item
     * @param ?string $description text of the item
     * @param ?string $url         link of the item: an http(s) address or a path of the site, as the back office takes it
     *
     * @return array{id: ?int, title: ?string, slug: ?string, description: ?string, url: ?string, position: int, image: bool}
     */
    public function create(int $collection, string $title, ?string $description = null, ?string $url = null): array
    {
        $this->access->guard('collections');
        $found = $this->foundCollection($collection);
        $item = new CollectionItem()
            ->setCollectionGroup($found)
            ->setTitle(trim($title))
            ->setSlug(trim($title))
            ->setDescription(self::text($description))
            ->setUrl(self::text($url))
            ->setPosition($this->collectionItemRepository->countByCollectionGroup($found))
            ->setUser($this->access->user());
        $this->collectionItemSlugs->apply($item);
        $this->validate($item, false);
        $this->entityManager->persist($item);
        $this->entityManager->flush();

        return self::item($item);
    }

    // An item's title, description or link changed, only those given, an empty description or link clearing it - its slug, image, place and publication left as they are
    /**
     * @param int     $item        id of the item
     * @param ?string $title       new title
     * @param ?string $description new text, empty to clear it
     * @param ?string $url         new link, an http(s) address or a path of the site, empty to clear it
     *
     * @return array{id: ?int, title: ?string, slug: ?string, description: ?string, url: ?string, position: int, image: bool}
     */
    public function edit(int $item, ?string $title = null, ?string $description = null, ?string $url = null): array
    {
        $this->access->guard('collections');
        $found = $this->collectionItemRepository->find($item);
        if (!$found instanceof CollectionItem) {
            throw new ToolCallException(sprintf('No collection item %d.', $item));
        }
        if ('' !== trim((string) $title)) {
            $found->setTitle(trim((string) $title));
        }
        if (null !== $description) {
            $found->setDescription(self::text($description));
        }
        if (null !== $url) {
            $found->setUrl(self::text($url));
        }
        $this->validate($found, true);
        $this->entityManager->flush();

        return self::item($found);
    }

    // A collection by its id
    private function foundCollection(int $id): CollectionGroup
    {
        $collection = $this->collectionGroupRepository->find($id);
        if (!$collection instanceof CollectionGroup) {
            throw new ToolCallException(sprintf('No collection %d.', $id));
        }

        return $collection;
    }

    // The item checked by the constraints of its entity as the screen's form runs them, every violation named, a refused change taken back off the managed row
    private function validate(CollectionItem $item, bool $managed): void
    {
        $violations = $this->validator->validate($item);
        if (0 === \count($violations)) {
            return;
        }
        if ($managed) {
            $this->entityManager->refresh($item);
        }
        $messages = [];
        foreach ($violations as $violation) {
            $messages[] = $violation->getPropertyPath() . ': ' . $violation->getMessage();
        }

        throw new ToolCallException(implode(' ', $messages));
    }

    // A text trimmed, null when nothing is left
    private static function text(?string $text): ?string
    {
        $text = trim((string) $text);

        return '' === $text ? null : $text;
    }

    // A collection as the tools give it
    /** @return array{id: ?int, name: ?string, slug: ?string} */
    private static function collection(CollectionGroup $collection): array
    {
        return ['id' => $collection->getId(), 'name' => $collection->getName(), 'slug' => $collection->getSlug()];
    }

    // An item as the tools give it, whether it has an image
    /** @return array{id: ?int, title: ?string, slug: ?string, description: ?string, url: ?string, position: int, image: bool} */
    private static function item(CollectionItem $item): array
    {
        return [
            'id' => $item->getId(),
            'title' => $item->getTitle(),
            'slug' => $item->getSlug(),
            'description' => $item->getDescription(),
            'url' => $item->getUrl(),
            'position' => $item->getPosition(),
            'image' => '' !== (string) $item->getFilename(),
        ];
    }
}
