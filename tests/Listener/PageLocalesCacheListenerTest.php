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
            ->method('invalidateTags')->with([PageTranslator::LOCALES_CACHE_TAG]);

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

        $this->listener(true)->postPersist(new PostPersistEventArgs($translation, $this->entityManager()));
    }

    public function testAPageTranslationEditedInvalidates(): void
    {
        $translation = new Translation(PageTranslator::OWNER, 12, 'title', 'en');

        $this->listener(true)->postUpdate(new PostUpdateEventArgs($translation, $this->entityManager()));
    }

    // A removed page takes its translations along by a DQL delete, which no event reports
    public function testARemovedPageInvalidates(): void
    {
        $this->listener(true)->preRemove(new PreRemoveEventArgs(new Page(), $this->entityManager()));
    }

    // A block's translation says nothing of the languages a page exists in
    public function testAnotherOwnersTranslationIsIgnored(): void
    {
        $translation = new Translation(Translation::OWNER_BLOCK, 3, 'title', 'en');

        $this->listener(false)->postPersist(new PostPersistEventArgs($translation, $this->entityManager()));
    }
}
