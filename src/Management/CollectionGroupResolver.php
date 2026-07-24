<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management;

use c975L\SiteBundle\Controller\Management\Trait\UniqueSlugTrait;
use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Repository\CollectionGroupRepository;
use Symfony\Component\String\Slugger\SluggerInterface;

// Shared by CollectionItemImportProvider (Sync import) and CollectionItemImportCommand (legacy JSON import) - both used to match an existing collection differently (exact name vs normalized slug), letting the same input name resolve to two different/duplicate CollectionGroup rows depending on which entry point ran the import
class CollectionGroupResolver
{
    use UniqueSlugTrait;

    public function __construct(
        private readonly CollectionGroupRepository $collectionGroupRepository,
        private readonly SluggerInterface $slugger,
    ) {
    }

    // Never persists - the caller decides (CollectionItemImportCommand skips persist() on --dry-run). $usedSlugs tracks slugs already allocated to a not-yet-flushed CollectionGroup within the same run, since findOneBySlug() can't see them until flush(). Returns [CollectionGroup, isNew] - both callers need to know whether it's freshly created (never persisted, so deterministically has zero existing items) rather than an existing one just fetched
    public function resolve(string $name, array &$usedSlugs): array
    {
        $slug = strtolower($this->slugger->slug($name)->toString());
        $collectionGroup = $this->collectionGroupRepository->findOneBySlug($slug);
        if (null !== $collectionGroup) {
            return [$collectionGroup, false];
        }

        $uniqueSlug = $this->uniqueSlug(
            $this->slugger,
            $name,
            fn (string $candidate): bool => isset($usedSlugs[$candidate]) || null !== $this->collectionGroupRepository->findOneBySlug($candidate)
        );
        $usedSlugs[$uniqueSlug] = true;

        return [(new CollectionGroup())->setName($name)->setSlug($uniqueSlug), true];
    }
}
