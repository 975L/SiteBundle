<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\SiteBundle\Entity\CollectionItem;
use Symfony\Component\Validator\Validator\ValidatorInterface;

// Shared by CollectionItemImportProvider (Sync import) and CollectionItemImportCommand (legacy JSON import), which write straight to the entity: an imported url goes through the constraints of CollectionItem::$url as a typed one does - a path of the site or a page target staying, unlike what an http-only check would leave
class CollectionItemUrlWriter
{
    public function __construct(
        private readonly ValidatorInterface $validator,
    ) {
    }

    // Writes $url on the item, or no url at all when it would not pass - returns whether it was kept
    public function write(CollectionItem $collectionItem, mixed $url): bool
    {
        if (null !== $url && !\is_string($url)) {
            $collectionItem->setUrl(null);

            return false;
        }

        $collectionItem->setUrl($url);
        if (0 === \count($this->validator->validateProperty($collectionItem, 'url'))) {
            return true;
        }

        $collectionItem->setUrl(null);

        return false;
    }
}
