<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\ImportProviderInterface;
use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use Doctrine\ORM\EntityManagerInterface;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// Imports a "site_collection_item" content export (see CollectionItemExportProvider) - matches by collection+slug, CollectionItem's own unique constraint, always overwrites the file like FontImportProvider does. A referenced collection that doesn't exist yet on this environment is created on the fly (by name), same as CollectionItemImportCommand's own auto-creation - so importing a fresh site's content never needs a manual "create the collections first" step
class CollectionItemImportProvider implements ImportProviderInterface
{
    public const KIND = 'site_collection_item';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly CollectionItemRepository $collectionItemRepository,
        private readonly CollectionGroupResolver $collectionGroupResolver,
    ) {
    }

    public function supportsImport(string $kind): bool
    {
        return self::KIND === $kind;
    }

    public function import(array $items, ?string $filesDir = null): array
    {
        $created = 0;
        $updated = 0;
        // Resolved/created collections, keyed by name - avoids creating the same not-yet-flushed CollectionGroup twice when several items in a row belong to a collection absent from this environment (see exportAll()'s own "ordered by collectionGroup" grouping)
        $collectionGroups = [];
        // Slugs already allocated to a not-yet-flushed CollectionGroup in this batch - findOneBySlug() can't see them until flush(), so two different names slugifying identically would otherwise both get the same slug
        $usedSlugs = [];

        foreach ($items as $item) {
            $collectionGroup = $collectionGroups[$item['collectionGroup']] ??= $this->resolveCollectionGroup($item['collectionGroup'], $usedSlugs);
            $collectionItem = $this->collectionItemRepository->findOneByCollectionGroupAndSlug($collectionGroup, $item['slug']);
            $isNew = null === $collectionItem;
            $collectionItem ??= new CollectionItem();

            $collectionItem
                ->setCollectionGroup($collectionGroup)
                ->setTitle($item['title'])
                ->setSlug($item['slug'])
                ->setDescription($item['description'] ?? null)
                ->setUrl($item['url'] ?? null)
                ->setPosition($item['position'] ?? 0);

            if (null !== $filesDir && isset($item['file'])) {
                $collectionItem->setFile(new ReplacingFile($filesDir . '/' . $item['file'], true, true, true));
            }

            $this->em->persist($collectionItem);
            $isNew ? $created++ : $updated++;
        }

        $this->em->flush();

        return ['created' => $created, 'updated' => $updated];
    }

    private function resolveCollectionGroup(string $name, array &$usedSlugs): CollectionGroup
    {
        [$collectionGroup, $isNew] = $this->collectionGroupResolver->resolve($name, $usedSlugs);
        if ($isNew) {
            $this->em->persist($collectionGroup);
        }

        return $collectionGroup;
    }
}
