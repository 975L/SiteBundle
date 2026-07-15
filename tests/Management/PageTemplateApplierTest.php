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
use c975L\SiteBundle\Management\PageTemplateApplier;
use PHPUnit\Framework\TestCase;

class PageTemplateApplierTest extends TestCase
{
    private function template(): array
    {
        return [
            'label' => 'label.test',
            'blocks' => [
                ['kind' => 'hero', 'data' => ['title' => 'Hello']],
                ['kind' => 'cta_band', 'data' => ['title' => 'Contact us']],
            ],
        ];
    }

    public function testApplyAddsOneBlockPerTemplateEntryInOrder(): void
    {
        $page = new Page();

        $count = (new PageTemplateApplier())->apply($page, $this->template());

        $this->assertSame(2, $count);
        $blocks = $page->getBlocks()->toArray();
        $this->assertCount(2, $blocks);
        $this->assertSame('hero', $blocks[0]->getKind());
        $this->assertSame(['title' => 'Hello'], $blocks[0]->getData());
        $this->assertSame(0, $blocks[0]->getPosition());
        $this->assertSame('cta_band', $blocks[1]->getKind());
        $this->assertSame(1, $blocks[1]->getPosition());
    }

    // Appends after whatever the page already has, instead of replacing it - positions continue
    // from the existing block count
    public function testApplyAppendsAfterExistingBlocksWithoutTouchingThem(): void
    {
        $page = new Page();
        $page->addBlock((new \c975L\UiBundle\Entity\Block())->setKind('legacy_content')->setPosition(0));

        (new PageTemplateApplier())->apply($page, $this->template());

        $blocks = $page->getBlocks()->toArray();
        $this->assertCount(3, $blocks);
        $this->assertSame('legacy_content', $blocks[0]->getKind());
        $this->assertSame('hero', $blocks[1]->getKind());
        $this->assertSame(1, $blocks[1]->getPosition());
        $this->assertSame(2, $blocks[2]->getPosition());
    }

    public function testApplyReturnsTheNumberOfBlocksAdded(): void
    {
        $count = (new PageTemplateApplier())->apply(new Page(), $this->template());

        $this->assertSame(2, $count);
    }

    // build() is the transient counterpart used by ?preset=X's demo preview (PageController::preview()):
    // same Block objects as apply(), but attached to no Page
    public function testBuildReturnsTransientBlocksInOrderWithoutAPage(): void
    {
        $blocks = (new PageTemplateApplier())->build($this->template());

        $this->assertCount(2, $blocks);
        $this->assertSame('hero', $blocks[0]->getKind());
        $this->assertSame(['title' => 'Hello'], $blocks[0]->getData());
        $this->assertSame(0, $blocks[0]->getPosition());
        $this->assertSame('cta_band', $blocks[1]->getKind());
        $this->assertSame(1, $blocks[1]->getPosition());
    }

    // startPosition lets a caller offset the built blocks, same as apply() does internally
    public function testBuildHonoursStartPosition(): void
    {
        $blocks = (new PageTemplateApplier())->build($this->template(), 5);

        $this->assertSame(5, $blocks[0]->getPosition());
        $this->assertSame(6, $blocks[1]->getPosition());
    }
}
