<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Listener;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Service\PageTranslator;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Listener\AbstractBlockCacheInvalidationListener;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// Drops the languages PageTranslator::translatedLocales() keeps cached when a page title is translated, emptied or removed - and when a page itself is removed, its translations going with it by a DQL delete no event reports (see PageTranslationPurgeListener)
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
class PageLocalesCacheListener extends AbstractBlockCacheInvalidationListener
{
    public function __construct(
        private readonly TagAwareCacheInterface $cache,
    ) {
    }

    protected function invalidate(object $entity): void
    {
        if ($entity instanceof Page || ($entity instanceof Translation && PageTranslator::OWNER === $entity->getOwnerType())) {
            $this->cache->invalidateTags([PageTranslator::LOCALES_CACHE_TAG]);
        }
    }
}
