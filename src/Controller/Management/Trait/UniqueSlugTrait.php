<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Controller\Management\Trait;

use Symfony\Component\String\Slugger\SluggerInterface;

// Normalizes a raw slug (accents, spaces, case) and appends -2, -3... until $collides() reports the
// candidate free - shared by PageCrudController (uniqueness site-wide) and CollectionEntryCrudController
// (uniqueness scoped to the entry's own group), so the suffixing algorithm only needs fixing once
trait UniqueSlugTrait
{
    private function uniqueSlug(SluggerInterface $slugger, string $base, callable $collides): string
    {
        $slug = strtolower($slugger->slug($base)->toString());
        $candidate = $slug;
        for ($i = 2; $collides($candidate); $i++) {
            $candidate = $slug . '-' . $i;
        }

        return $candidate;
    }
}
