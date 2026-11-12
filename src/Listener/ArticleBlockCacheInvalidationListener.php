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
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PageTranslator;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Listener\PostFlushTagInvalidationTrait;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\Events;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// UiBundle's BlockCacheInvalidationListener only invalidates the changed Block's own "block_{id}" tag - it has no idea that an articles_slider block elsewhere renders this Page's "article" blocks (see ArticlesSliderCacheTagProvider for the matching "page_{id}" tag). This listener closes that gap: an article block or its image changing, the page itself (status, slug, members only) or its translated title changing, each empties "page_{id}" once the flush is committed
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::preRemove)]
#[AsDoctrineListener(event: Events::postFlush)]
class ArticleBlockCacheInvalidationListener
{
    // Emptied after the commit, or a concurrent request would cache the old slider again
    use PostFlushTagInvalidationTrait {
        postFlush as private invalidateQueuedTags;
    }

    // Article blocks created or edited during the flush, whose page is looked up once it is committed: a new block's row in site_page_blocks is not written yet at postPersist
    /** @var array<int, true> */
    private array $pendingBlockIds = [];

    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly TagAwareCacheInterface $cache,
    ) {
    }

    // The pool the tags are emptied from
    private function tagCache(): TagAwareCacheInterface
    {
        return $this->cache;
    }

    // A block, an image or a page created
    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->note($args->getObject(), $args->getObjectManager(), false);
    }

    // A block, an image or a page edited
    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->note($args->getObject(), $args->getObjectManager(), false);
    }

    // A removed block's page is looked up right away, its row in site_page_blocks being gone once the flush is committed
    public function preRemove(PreRemoveEventArgs $args): void
    {
        $this->note($args->getObject(), $args->getObjectManager(), true);
    }

    // Resolves the pages of the blocks noted, then empties every tag gathered
    public function postFlush(): void
    {
        if ([] !== $this->pendingBlockIds) {
            $blockIds = array_keys($this->pendingBlockIds);
            $this->pendingBlockIds = [];
            $this->queuePagesOf($blockIds);
        }

        $this->invalidateQueuedTags();
    }

    // What the entity changes of a slider: its own page, the page it translates, or the article block it is or hangs from
    private function note(object $entity, EntityManagerInterface $em, bool $resolveNow): void
    {
        if ($entity instanceof Page && null !== $entity->getId()) {
            $this->queueTags(['page_' . $entity->getId()]);

            return;
        }

        if ($entity instanceof Translation && PageTranslator::OWNER === $entity->getOwnerType()) {
            $this->queueTags(['page_' . $entity->getOwnerId()]);

            return;
        }

        $block = match (true) {
            $entity instanceof Block => $entity,
            $entity instanceof Media => $this->mediaBlock($entity, $em),
            default => null,
        };

        if (null === $block || 'article' !== $block->getKind() || null === $block->getId()) {
            return;
        }

        if ($resolveNow) {
            $this->queuePagesOf([$block->getId()]);

            return;
        }

        $this->pendingBlockIds[$block->getId()] = true;
    }

    // The block an image hangs from - read off the change set when Block::removeMedia() already nulled it, as UiBundle's BlockCacheInvalidationListener does
    private function mediaBlock(Media $media, EntityManagerInterface $em): ?Block
    {
        $block = $media->getBlock() ?? ($em->getUnitOfWork()->getEntityChangeSet($media)['block'][0] ?? null);

        return $block instanceof Block ? $block : null;
    }

    // Every page holding one of these blocks
    /** @param int[] $blockIds */
    private function queuePagesOf(array $blockIds): void
    {
        foreach ($this->pageRepository->findByBlockIds($blockIds) as $page) {
            $this->queueTags(['page_' . $page->getId()]);
        }
    }
}
