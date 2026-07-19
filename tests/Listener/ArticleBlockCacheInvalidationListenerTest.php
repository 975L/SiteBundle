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
use c975L\UiBundle\Entity\Block;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class ArticleBlockCacheInvalidationListenerTest extends TestCase
{
    private function createBlock(string $kind, ?int $id): Block
    {
        $block = (new Block())->setKind($kind);
        if (null !== $id) {
            (new \ReflectionProperty(Block::class, 'id'))->setValue($block, $id);
        }

        return $block;
    }

    private function createPage(int $id): Page
    {
        $page = new Page();
        (new \ReflectionProperty(Page::class, 'id'))->setValue($page, $id);

        return $page;
    }

    // articles_slider resolves another Page's "article" blocks live at render time (see ArticlesSliderCacheTagProvider) - editing an article block must invalidate every page referencing it, so any articles_slider pointing there re-renders fresh next time
    public function testPostUpdateInvalidatesEveryPageOwningTheChangedArticleBlock(): void
    {
        $block = $this->createBlock('article', 12);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findByBlockIds')->willReturn([$this->createPage(3)]);

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['page_3']);

        (new ArticleBlockCacheInvalidationListener($pageRepository, $cache))
            ->postUpdate(new PostUpdateEventArgs($block, $this->createStub(EntityManagerInterface::class)));
    }

    public function testPostPersistInvalidatesEveryPageOwningTheNewArticleBlock(): void
    {
        $block = $this->createBlock('article', 21);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findByBlockIds')->willReturn([$this->createPage(5), $this->createPage(6)]);

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->exactly(2))->method('invalidateTags')
            ->with($this->logicalOr(['page_5'], ['page_6']));

        (new ArticleBlockCacheInvalidationListener($pageRepository, $cache))
            ->postPersist(new PostPersistEventArgs($block, $this->createStub(EntityManagerInterface::class)));
    }

    public function testPreRemoveInvalidatesEveryPageOwningTheRemovedArticleBlock(): void
    {
        $block = $this->createBlock('article', 8);

        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findByBlockIds')->willReturn([$this->createPage(9)]);

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->once())->method('invalidateTags')->with(['page_9']);

        (new ArticleBlockCacheInvalidationListener($pageRepository, $cache))
            ->preRemove(new PreRemoveEventArgs($block, $this->createStub(EntityManagerInterface::class)));
    }

    public function testInvalidateIsSkippedForBlocksOfAnotherKind(): void
    {
        $block = $this->createBlock('articles_slider', 1);

        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->expects($this->never())->method('findByBlockIds');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('invalidateTags');

        (new ArticleBlockCacheInvalidationListener($pageRepository, $cache))
            ->postUpdate(new PostUpdateEventArgs($block, $this->createStub(EntityManagerInterface::class)));
    }

    public function testInvalidateIsSkippedForEntitiesThatAreNotBlocks(): void
    {
        $pageRepository = $this->createMock(PageRepository::class);
        $pageRepository->expects($this->never())->method('findByBlockIds');

        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('invalidateTags');

        (new ArticleBlockCacheInvalidationListener($pageRepository, $cache))
            ->postUpdate(new PostUpdateEventArgs(new \stdClass(), $this->createStub(EntityManagerInterface::class)));
    }
}
