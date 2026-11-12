<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Listener;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Listener\ArticleBlockCacheInvalidationListener;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PageTranslator;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use c975L\UiBundle\Entity\Translation;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class ArticleBlockCacheInvalidationListenerTest extends TestCase
{
    // A block of that kind, with the id the database would have given it
    private function createBlock(string $kind, ?int $id): Block
    {
        $block = new Block()->setKind($kind);
        if (null !== $id) {
            new \ReflectionProperty(Block::class, 'id')->setValue($block, $id);
        }

        return $block;
    }

    // A page carrying that id
    private function createPage(int $id): Page
    {
        $page = new Page();
        new \ReflectionProperty(Page::class, 'id')->setValue($page, $id);

        return $page;
    }

    // A cache expecting these tags once, at the commit
    private function cacheExpecting(array $tags): TagAwareCacheInterface
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with($tags);

        return $cache;
    }

    // An entity manager whose change set gives back the block a media was taken out of
    private function entityManager(?Block $formerBlock = null): EntityManagerInterface
    {
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getEntityChangeSet')->willReturn(null !== $formerBlock ? ['block' => [$formerBlock, null]] : []);
        $em = $this->createStub(EntityManagerInterface::class);
        $em->method('getUnitOfWork')->willReturn($unitOfWork);

        return $em;
    }

    // articles_slider resolves another Page's "article" blocks at render time (see ArticlesSliderCacheTagProvider) - editing one empties every page holding it, once committed
    public function testPostUpdateInvalidatesEveryPageOwningTheChangedArticleBlock(): void
    {
        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findByBlockIds')->willReturn([$this->createPage(3)]);

        $listener = new ArticleBlockCacheInvalidationListener($pageRepository, $this->cacheExpecting(['page_3']));
        $listener->postUpdate(new PostUpdateEventArgs($this->createBlock('article', 12), $this->entityManager()));
        $listener->postFlush();
    }

    // A new block's row in site_page_blocks is written after its postPersist: its page is looked up once the flush is committed, not before
    public function testANewArticleBlockIsResolvedAtPostFlush(): void
    {
        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->expects($this->once())->method('findByBlockIds')->with([21])->willReturn([$this->createPage(5), $this->createPage(6)]);

        $listener = new ArticleBlockCacheInvalidationListener($pageRepository, $this->cacheExpecting(['page_5', 'page_6']));
        $listener->postPersist(new PostPersistEventArgs($this->createBlock('article', 21), $this->entityManager()));
        $listener->postFlush();
    }

    // A removed block's page is read while its row still links them
    public function testPreRemoveResolvesThePageRightAway(): void
    {
        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->expects($this->once())->method('findByBlockIds')->with([8])->willReturn([$this->createPage(9)]);

        $listener = new ArticleBlockCacheInvalidationListener($pageRepository, $this->cacheExpecting(['page_9']));
        $listener->preRemove(new PreRemoveEventArgs($this->createBlock('article', 8), $this->entityManager()));
        $listener->postFlush();
    }

    // A replaced image is an update on the Media alone, which UiBundle reports under the block's own tag only
    public function testAnArticleImageReplacedInvalidatesItsPage(): void
    {
        $media = new Media()->setBlock($this->createBlock('article', 14));

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findByBlockIds')->willReturn([$this->createPage(2)]);

        $listener = new ArticleBlockCacheInvalidationListener($pageRepository, $this->cacheExpecting(['page_2']));
        $listener->postUpdate(new PostUpdateEventArgs($media, $this->entityManager()));
        $listener->postFlush();
    }

    // A removed image no longer names its block, the change set still does
    public function testAnArticleImageRemovedIsFoundThroughTheChangeSet(): void
    {
        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findByBlockIds')->willReturn([$this->createPage(4)]);

        $listener = new ArticleBlockCacheInvalidationListener($pageRepository, $this->cacheExpecting(['page_4']));
        $listener->preRemove(new PreRemoveEventArgs(new Media(), $this->entityManager($this->createBlock('article', 15))));
        $listener->postFlush();
    }

    // The page itself going members only, unpublished or renamed changes what its slider may show
    public function testThePageItselfInvalidatesItsTag(): void
    {
        $listener = new ArticleBlockCacheInvalidationListener($this->createStub(PageRepository::class), $this->cacheExpecting(['page_7']));
        $listener->postUpdate(new PostUpdateEventArgs($this->createPage(7), $this->entityManager()));
        $listener->postFlush();
    }

    // A page translated in a new language changes the url the slider links to in it
    public function testAPageTranslationInvalidatesItsTag(): void
    {
        $listener = new ArticleBlockCacheInvalidationListener($this->createStub(PageRepository::class), $this->cacheExpecting(['page_11']));
        $listener->postPersist(new PostPersistEventArgs(new Translation(PageTranslator::OWNER, 11, 'title', 'en'), $this->entityManager()));
        $listener->postFlush();
    }

    // Another kind, or something that is not a block, empties nothing
    public function testInvalidateIsSkippedForAnythingElse(): void
    {
        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->expects($this->never())->method('findByBlockIds');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('invalidateTags');

        $listener = new ArticleBlockCacheInvalidationListener($pageRepository, $cache);
        $listener->postUpdate(new PostUpdateEventArgs($this->createBlock('articles_slider', 1), $this->entityManager()));
        $listener->postUpdate(new PostUpdateEventArgs(new \stdClass(), $this->entityManager()));
        $listener->postFlush();
    }
}
