<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Listener;

use c975L\SiteBundle\Entity\CollectionGroup;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Listener\ArticleBlockCacheInvalidationListener;
use c975L\SiteBundle\Listener\CollectionCacheInvalidationListener;
use c975L\SiteBundle\Listener\MenuCacheInvalidationListener;
use c975L\SiteBundle\Listener\PageLocalesCacheListener;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Entity\Block;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

// The cache listeners empty their tags once the flush is committed: inside the transaction, a concurrent request would cache the old state again, with no expiry
class PostFlushInvalidationTest extends TestCase
{
    /** @return iterable<string, array{class-string}> */
    public static function listeners(): iterable
    {
        yield 'article' => [ArticleBlockCacheInvalidationListener::class];
        yield 'collection' => [CollectionCacheInvalidationListener::class];
        yield 'menu' => [MenuCacheInvalidationListener::class];
        yield 'page locales' => [PageLocalesCacheListener::class];
    }

    // Without the attribute, the tags gathered would never be emptied outside a test calling postFlush() by hand
    #[DataProvider('listeners')]
    public function testTheListenerIsWiredOnPostFlush(string $class): void
    {
        $events = array_map(
            static fn (\ReflectionAttribute $attribute): string => $attribute->newInstance()->event,
            new \ReflectionClass($class)->getAttributes(AsDoctrineListener::class)
        );

        $this->assertContains(Events::postFlush, $events);
    }

    // An event during the flush gathers the tag and empties nothing yet
    #[DataProvider('listeners')]
    public function testNothingIsInvalidatedBeforeTheFlushIsCommitted(string $class): void
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($this->never())->method('invalidateTags');

        $page = new Page();
        new \ReflectionProperty(Page::class, 'id')->setValue($page, 3);
        $collectionGroup = new CollectionGroup();
        new \ReflectionProperty(CollectionGroup::class, 'id')->setValue($collectionGroup, 4);
        $entity = match ($class) {
            MenuCacheInvalidationListener::class => new Block()->setKind('menu_link'),
            CollectionCacheInvalidationListener::class => $collectionGroup,
            default => $page,
        };

        $listener = match ($class) {
            ArticleBlockCacheInvalidationListener::class => new ArticleBlockCacheInvalidationListener($this->createStub(PageRepository::class), $cache),
            CollectionCacheInvalidationListener::class => new CollectionCacheInvalidationListener($cache, $this->createStub(CollectionItemRepository::class)),
            default => new $class($cache),
        };

        $listener->postUpdate(new PostUpdateEventArgs($entity, $this->createStub(EntityManagerInterface::class)));
    }
}
