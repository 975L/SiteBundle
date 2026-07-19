<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\SiteBlockEditUrlProvider;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Entity\Block;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

class SiteBlockEditUrlProviderTest extends TestCase
{
    private function createPageRepository(array $pagesOwningBlocks): PageRepository
    {
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findByBlockIds')->willReturn($pagesOwningBlocks);

        return $repository;
    }

    private function createAdminUrlGenerator(): AdminUrlGeneratorInterface
    {
        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setController')->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('setEntityId')->willReturnSelf();
        $generator->method('set')->willReturnSelf();
        $generator->method('generateUrl')->willReturn('/admin/edit');

        return $generator;
    }

    private function blockWithId(int $id): Block
    {
        $block = new Block();
        (new \ReflectionProperty(Block::class, 'id'))->setValue($block, $id);

        return $block;
    }

    // A block owned by a known Page resolves to that Page's edit URL
    public function testGetEditUrlsResolvesUrlForBlockOwnedByPage(): void
    {
        $block = $this->blockWithId(10);

        $page = new Page();
        $page->addBlock($block);

        $provider = new SiteBlockEditUrlProvider($this->createPageRepository([$page]), $this->createAdminUrlGenerator());

        $this->assertSame([10 => '/admin/edit'], $provider->getEditUrls([$block]));
    }

    // A block with no owning Page (not found by findByBlockIds) resolves to nothing - no error
    public function testGetEditUrlsReturnsEmptyArrayForUnownedBlock(): void
    {
        $block = $this->blockWithId(20);

        $provider = new SiteBlockEditUrlProvider($this->createPageRepository([]), $this->createAdminUrlGenerator());

        $this->assertSame([], $provider->getEditUrls([$block]));
    }

    // Passing no blocks skips the repository query entirely
    public function testGetEditUrlsReturnsEmptyArrayForNoBlocks(): void
    {
        $provider = new SiteBlockEditUrlProvider($this->createPageRepository([]), $this->createAdminUrlGenerator());

        $this->assertSame([], $provider->getEditUrls([]));
    }
}
