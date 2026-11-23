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
use c975L\UiBundle\Service\UniqueSlug;
use Symfony\Component\String\Slugger\SluggerInterface;

// The slug of a collection item, normalized and kept unique within its own collection - shared by CollectionItemCrudController and the agent's tools (see SiteCollectionTools), unlike Page::$slug which is unique site-wide
class CollectionItemSlugs
{
    public function __construct(
        private readonly CollectionItemRepository $collectionItemRepository,
        private readonly SluggerInterface $slugger,
    ) {
    }

    // Normalizes the slug (removes accents, spaces, uppercase...) and appends -2, -3... on collision, an item without a slug left as it is
    public function apply(CollectionItem $collectionItem): void
    {
        $slug = $collectionItem->getSlug();
        if (null === $slug) {
            return;
        }

        $collectionItem->setSlug(UniqueSlug::build(
            $this->slugger,
            $slug,
            fn (string $candidate): bool => $this->collides($collectionItem, $candidate)
        ));
    }

    // Another item of the same collection holding the candidate, an item without a collection never colliding
    private function collides(CollectionItem $collectionItem, string $candidate): bool
    {
        $collectionGroup = $collectionItem->getCollectionGroup();
        if (null === $collectionGroup) {
            return false;
        }

        $existing = $this->collectionItemRepository->findOneByCollectionGroupAndSlug($collectionGroup, $candidate);

        return null !== $existing && $existing->getId() !== $collectionItem->getId();
    }
}
