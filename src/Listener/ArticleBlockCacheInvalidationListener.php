<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Listener;

use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Entity\Block;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Events;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// UiBundle's BlockCacheInvalidationListener only invalidates the directly changed Block's own
// "block_{id}" tag - it has no idea that an articles_slider block elsewhere depends on this Page's
// "article" blocks (see ArticlesSliderCacheTagProvider for the matching "page_{id}" tag applied at
// render time). This listener closes that gap: whenever an "article" block changes, it invalidates
// every Page that owns it, so any articles_slider pointing at that page re-renders fresh next time
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
class ArticleBlockCacheInvalidationListener
{
    public function __construct(
        private PageRepository $pageRepository,
        private TagAwareCacheInterface $cache
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->invalidate($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->invalidate($args->getObject());
    }

    public function preRemove(PreRemoveEventArgs $args): void
    {
        $this->invalidate($args->getObject());
    }

    private function invalidate(object $entity): void
    {
        if (!$entity instanceof Block || 'article' !== $entity->getKind() || null === $entity->getId()) {
            return;
        }

        foreach ($this->pageRepository->findByBlockIds([$entity->getId()]) as $page) {
            $this->cache->invalidateTags(['page_' . $page->getId()]);
        }
    }
}
