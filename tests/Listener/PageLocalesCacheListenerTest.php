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
use c975L\SiteBundle\Listener\PageLocalesCacheListener;
use c975L\SiteBundle\Service\PageTranslator;
use c975L\UiBundle\Entity\Translation;
use c975L\UiBundle\Service\LegalModelCacheTagProvider;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Event\PreRemoveEventArgs;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Cache\TagAwareCacheInterface;

class PageLocalesCacheListenerTest extends TestCase
{
    private function listener(bool $expectsInvalidation): PageLocalesCacheListener
    {
        $cache = $this->createMock(TagAwareCacheInterface::class);
        $cache->expects($expectsInvalidation ? $this->once() : $this->never())
            ->method('invalidateTags')->with([PageTranslator::LOCALES_CACHE_TAG, LegalModelCacheTagProvider::CACHE_TAG]);

        return new PageLocalesCacheListener($cache);
    }

    private function entityManager(): EntityManagerInterface
    {
        return $this->createStub(EntityManagerInterface::class);
    }

    // A title translated into a new language opens that language for the page
    public function testAPageTranslationWrittenInvalidates(): void
    {
        $translation = new Translation(PageTranslator::OWNER, 12, 'title', 'en');

        $listener = $this->listener(true);
        $listener->postPersist(new PostPersistEventArgs($translation, $this->entityManager()));
        $listener->postFlush();
    }

    public function testAPageTranslationEditedInvalidates(): void
    {
        $translation = new Translation(PageTranslator::OWNER, 12, 'title', 'en');

        $listener = $this->listener(true);
        $listener->postUpdate(new PostUpdateEventArgs($translation, $this->entityManager()));
        $listener->postFlush();
    }

    // A page's title, status or audience is what a legal model's "Annexes" list shows
    public function testAnEditedPageInvalidatesTheLegalModels(): void
    {
        $listener = $this->listener(true);
        $listener->postUpdate(new PostUpdateEventArgs(new Page(), $this->entityManager()));
        $listener->postFlush();
    }

    // A removed page takes its translations along by a DQL delete, which no event reports
    public function testARemovedPageInvalidates(): void
    {
        $listener = $this->listener(true);
        $listener->preRemove(new PreRemoveEventArgs(new Page(), $this->entityManager()));
        $listener->postFlush();
    }

    // A block's translation says nothing of the languages a page exists in
    public function testAnotherOwnersTranslationIsIgnored(): void
    {
        $translation = new Translation(Translation::OWNER_BLOCK, 3, 'title', 'en');

        $listener = $this->listener(false);
        $listener->postPersist(new PostPersistEventArgs($translation, $this->entityManager()));
        $listener->postFlush();
    }
}
