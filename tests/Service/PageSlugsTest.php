<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\ConfigBundle\Repository\RedirectRepository;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PageSlugs;
use c975L\SiteBundle\Service\PageTranslator;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use PHPUnit\Framework\TestCase;
use Symfony\Component\String\Slugger\AsciiSlugger;

class PageSlugsTest extends TestCase
{
    // Accents, spaces and case normalized
    public function testNormalizeAccentsSpacesAndCase(): void
    {
        $page = new Page()->setTitle('x')->setSlug('Héllo Wörld!');

        $this->pageSlugs()->normalize($page);

        $this->assertSame('hello-world', $page->getSlug());
    }

    // A slug another page holds gets a number, the page's own one kept
    public function testUniqueSkipsAnotherPageOnly(): void
    {
        $other = $this->createStub(Page::class);
        $other->method('getId')->willReturn(7);
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?Page => 'item-1' === $criteria['slug'] ? $other : null);

        $this->assertSame('item-1-2', $this->pageSlugs($repository)->unique('Item 1', null));
        $this->assertSame('item-1', $this->pageSlugs($repository)->unique('Item 1', $other));
    }

    // An unpublished page renamed moves its slug and leaves no redirect behind
    public function testPrepareUpdateOfAnUnpublishedPageRedirectsNothing(): void
    {
        $page = new Page()->setTitle('Item 2')->setSlug('item-1');
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn(['slug' => 'item-1', 'title' => 'Item 1', 'isPublished' => false]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);
        $entityManager->expects($this->never())->method('persist');

        $this->pageSlugs()->prepareUpdate($entityManager, $page);

        $this->assertSame('item-2', $page->getSlug());
    }

    // The real service on stubbed repositories, a site declaring one language
    private function pageSlugs(?PageRepository $repository = null): PageSlugs
    {
        return new PageSlugs($repository ?? $this->createStub(PageRepository::class), $this->createStub(RedirectRepository::class), new AsciiSlugger(), new SiteLocales([], 'fr'), $this->createStub(PageTranslator::class));
    }
}
