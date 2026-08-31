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
use c975L\SiteBundle\Listener\PageTranslationPurgeListener;
use c975L\SiteBundle\Service\PageTranslator;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Repository\TranslationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use PHPUnit\Framework\TestCase;

class PageTranslationPurgeListenerTest extends TestCase
{
    private function createEventArgs(object $entity): PostRemoveEventArgs
    {
        return new PostRemoveEventArgs($entity, $this->createStub(EntityManagerInterface::class));
    }

    private function createPage(?int $id): Page
    {
        $page = new Page()->setSlug('contact')->setTitle('Contact');
        if (null !== $id) {
            new \ReflectionProperty(Page::class, 'id')->setValue($page, $id);
        }

        return $page;
    }

    // Nothing points at a translation, so nothing else takes it away: a new page landing on this id would inherit the deleted one's translated title
    public function testAPageTakesItsTranslationsWithIt(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->once())
            ->method('deleteByOwner')
            ->with(PageTranslator::OWNER, 42)
            ->willReturn(2);

        new PageTranslationPurgeListener($repository)->postRemove($this->createEventArgs($this->createPage(42)));
    }

    // A block is UiBundle's own listener's business, and would otherwise be purged twice under two owner types
    public function testAnotherEntityIsLeftAlone(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->never())->method('deleteByOwner');

        new PageTranslationPurgeListener($repository)->postRemove($this->createEventArgs(new Block()->setKind('text')));
    }

    // A page that was never persisted has no id to delete rows by, and every row would answer to "null"
    public function testAPageWithoutAnIdIsLeftAlone(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->never())->method('deleteByOwner');

        new PageTranslationPurgeListener($repository)->postRemove($this->createEventArgs($this->createPage(null)));
    }
}
