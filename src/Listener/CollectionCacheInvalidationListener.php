<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Listener;

use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use c975L\SiteBundle\Service\CollectionItemSourceProvider;
use c975L\SiteBundle\Service\CollectionItemTranslator;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Listener\AbstractBlockCacheInvalidationListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// The other half of CollectionItemSourceProvider's "cacheTags": UiBundle caches a "collection" block and each of its items under the tag the source declared, and this is what invalidates it - editing an item, adding one, removing one, reordering them, renaming the collection they belong to, or translating an item.
// A CollectionItem's image is a Vich field on the item itself, so replacing it is an update on this very row - no separate Media event to watch, unlike the Block/Media pair BlockCacheInvalidationListener handles.
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
class CollectionCacheInvalidationListener extends AbstractBlockCacheInvalidationListener
{
    public function __construct(
        private readonly TagAwareCacheInterface $cache,
        private readonly CollectionItemRepository $collectionItemRepository,
    ) {
    }

    protected function invalidate(object $entity): void
    {
        // The group itself is watched too: its name is the source's own label, and deleting it cascades to items whose own preRemove would fire after this one anyway
        $collectionGroup = match (true) {
            $entity instanceof CollectionItem => $entity->getCollectionGroup(),
            $entity instanceof CollectionGroup => $entity,
            // An item's translation is a row of another table, so a language screen changes nothing of the item and its cards would go on being served in that language as they stood
            $entity instanceof Translation && CollectionItemTranslator::OWNER === $entity->getOwnerType() => $this->collectionItemRepository->find($entity->getOwnerId())?->getCollectionGroup(),
            default => null,
        };

        if (null !== $collectionGroup && null !== $collectionGroup->getId()) {
            $this->cache->invalidateTags([CollectionItemSourceProvider::CACHE_TAG_PREFIX . $collectionGroup->getId()]);
        }
    }
}
