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
use Doctrine\ORM\Event\PreRemoveEventArgs;
use PHPUnit\Framework\TestCase;

class PageTranslationPurgeListenerTest extends TestCase
{
    // What Doctrine does with a removal: preRemove while the row still has its id, which it hands back to null before postRemove
    private function remove(TranslationRepository $repository, object $entity): void
    {
        $listener = new PageTranslationPurgeListener($repository);
        $entityManager = $this->createStub(EntityManagerInterface::class);

        $listener->preRemove(new PreRemoveEventArgs($entity, $entityManager));
        new \ReflectionProperty($entity, 'id')->setValue($entity, null);
        $listener->postRemove(new PostRemoveEventArgs($entity, $entityManager));
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

        $this->remove($repository, $this->createPage(42));
    }

    // A block is UiBundle's own listener's business, and would otherwise be purged twice under two owner types
    public function testAnotherEntityIsLeftAlone(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->never())->method('deleteByOwner');

        $this->remove($repository, new Block()->setKind('text'));
    }

    // A page that was never persisted has no id to delete rows by, and every row would answer to "null"
    public function testAPageWithoutAnIdIsLeftAlone(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->never())->method('deleteByOwner');

        $this->remove($repository, $this->createPage(null));
    }

    // postRemove alone has no id left to go on, whatever the row still says: that is the very state Doctrine hands it
    public function testPostRemoveWithoutPreRemoveDeletesNothing(): void
    {
        $repository = $this->createMock(TranslationRepository::class);
        $repository->expects($this->never())->method('deleteByOwner');

        new PageTranslationPurgeListener($repository)->postRemove(new PostRemoveEventArgs($this->createPage(42), $this->createStub(EntityManagerInterface::class)));
    }
}
