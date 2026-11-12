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
use c975L\UiBundle\Listener\PostFlushTagInvalidationTrait;
use c975L\UiBundle\Service\LegalModelCacheTagProvider;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// Drops the languages PageTranslator::translatedLocales() keeps cached when a page title is translated, emptied or removed - and when a page itself is removed, its translations going with it by a DQL delete no event reports (see PageTranslationPurgeListener). The legal models' renders go with them: their "Annexes" list names the legal pages by title, status and audience (see PageExtension::getLegalPages()), UiBundle tagging them with LegalModelCacheTagProvider::CACHE_TAG alone
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
class PageLocalesCacheListener extends AbstractBlockCacheInvalidationListener
{
    // The tags are gathered on each event and emptied after the commit, or a concurrent request would cache the old state again
    use PostFlushTagInvalidationTrait;

    public function __construct(
        private readonly TagAwareCacheInterface $cache,
    ) {
    }

    // The pool the tags are emptied from
    private function tagCache(): TagAwareCacheInterface
    {
        return $this->cache;
    }

    // A page or its translated title changed
    protected function invalidate(object $entity): void
    {
        if ($entity instanceof Page || ($entity instanceof Translation && PageTranslator::OWNER === $entity->getOwnerType())) {
            $this->queueTags([PageTranslator::LOCALES_CACHE_TAG, LegalModelCacheTagProvider::CACHE_TAG]);
        }
    }
}
