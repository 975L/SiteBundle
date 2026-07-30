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
use c975L\SiteBundle\Management\PageBlockLocator;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;

class PageBlockLocatorTest extends TestCase
{
    // Every setter returns the generator itself (BlockFocusUrlTrait chains them), and generateUrl() echoes back whatever focusBlock was set - all this test cares about is which block was pointed at
    private function createLocator(): PageBlockLocator
    {
        $focusBlock = null;
        $generator = $this->createStub(AdminUrlGeneratorInterface::class);
        $generator->method('unsetAll')->willReturnSelf();
        $generator->method('setController')->willReturnSelf();
        $generator->method('setAction')->willReturnSelf();
        $generator->method('setEntityId')->willReturnSelf();
        $generator->method('set')->willReturnCallback(function (string $key, mixed $value) use ($generator, &$focusBlock) {
            $focusBlock = $value;

            return $generator;
        });
        $generator->method('generateUrl')->willReturnCallback(function () use (&$focusBlock): string {
            return '/management?focusBlock=' . $focusBlock;
        });

        return new PageBlockLocator($generator);
    }

    private function createBlock(int $id, string $kind, array $data = []): Block
    {
        $block = (new Block())->setKind($kind)->setPosition(1)->setData($data);
        (new \ReflectionProperty(Block::class, 'id'))->setValue($block, $id);

        return $block;
    }

    private function createPage(Block ...$blocks): Page
    {
        $page = (new Page())->setTitle('Home')->setSlug('home');
        (new \ReflectionProperty(Page::class, 'id'))->setValue($page, 5);
        foreach ($blocks as $block) {
            $page->addBlock($block);
        }

        return $page;
    }

    public function testLocateImageFindsTheBlockHoldingTheMedia(): void
    {
        $block = $this->createBlock(12, 'image');
        $block->addMedia((new Media())->setFilename('beach-holiday.jpg'));

        $located = $this->createLocator()->locateImage($this->createPage($this->createBlock(11, 'text'), $block), 'https://example.com/images/beach-holiday.jpg?1234');

        $this->assertSame('/management?focusBlock=12', $located['editUrl']);
        $this->assertStringContainsString('Image', $located['label']);
    }

    // The rendered src is a resized/thumbnail variant of the stored file, not the stored name verbatim
    public function testLocateImageMatchesAResizedVariantOfTheMedia(): void
    {
        $block = $this->createBlock(12, 'image');
        $block->addMedia((new Media())->setFilename('beach-holiday.jpg'));

        $located = $this->createLocator()->locateImage($this->createPage($block), '/media/cache/resolve/thumb/beach-holiday-800x600.jpg');

        $this->assertSame('/management?focusBlock=12', $located['editUrl']);
    }

    // A stored "photo-1" must not claim a rendered "photo-11": the stem only matches as a prefix followed by a separator, which is what every variant suffix starts with
    public function testLocateImageDoesNotMatchALongerFilenameSharingThePrefix(): void
    {
        $holder = $this->createBlock(12, 'image');
        $holder->addMedia((new Media())->setFilename('photo-1.webp'));
        $other = $this->createBlock(13, 'image');
        $other->addMedia((new Media())->setFilename('photo-11.webp'));

        $located = $this->createLocator()->locateImage($this->createPage($holder, $other), '/uploads/photo-11.webp');

        $this->assertSame('/management?focusBlock=13', $located['editUrl']);
    }

    // Same length floor as locateLink(): an image name that short claims a block only on an exact media match, never on a substring of its data
    public function testLocateImageIgnoresATooShortNeedleInTheBlockData(): void
    {
        $block = $this->createBlock(15, 'text', ['content' => '<p>See the <a href="/cv.pdf">cv</a></p>']);

        $this->assertNull($this->createLocator()->locateImage($this->createPage($block), '/uploads/cv.png'));
    }

    // An image written straight into a block's own data (a rich-text body) has no Media of its own
    public function testLocateImageFallsBackToTheBlockData(): void
    {
        $block = $this->createBlock(14, 'text', ['content' => '<p><img src="/uploads/schema-2026.png"></p>']);

        $this->assertSame('/management?focusBlock=14', $this->createLocator()->locateImage($this->createPage($block), '/uploads/schema-2026.png')['editUrl']);
    }

    public function testLocateImageReturnsNullWhenNoBlockClaimsIt(): void
    {
        $page = $this->createPage($this->createBlock(11, 'text', ['content' => 'Nothing here']));

        $this->assertNull($this->createLocator()->locateImage($page, '/bundles/c975lsite/logo.svg'));
    }

    public function testLocateLinkFindsTheBlockHoldingTheHref(): void
    {
        $block = $this->createBlock(34, 'cta', ['url' => '/pages/old-offer/', 'label' => 'Nos tarifs']);

        $located = $this->createLocator()->locateLink($this->createPage($block), 'https://example.com/pages/old-offer/');

        $this->assertSame('/management?focusBlock=34', $located['editUrl']);
    }

    // A link field pointing at another page can hold just that page's slug, not the full path
    public function testLocateLinkMatchesOnTheLastPathSegment(): void
    {
        $block = $this->createBlock(35, 'menu_link', ['target' => 'old-offer']);

        $this->assertSame('/management?focusBlock=35', $this->createLocator()->locateLink($this->createPage($block), 'https://example.com/pages/old-offer/')['editUrl']);
    }

    // A slot is rendered inside its parent but edited as a row of its own, so it's worth focusing directly
    public function testLocateLinkLooksInsideNestedSlots(): void
    {
        $parent = $this->createBlock(40, 'columns');
        $parent->addSlot($this->createBlock(41, 'cta', ['url' => '/pages/old-offer/']));

        $this->assertSame('/management?focusBlock=41', $this->createLocator()->locateLink($this->createPage($parent), '/pages/old-offer/')['editUrl']);
    }

    // "/cv" as a substring would match any block whose data merely contains those two letters
    public function testLocateLinkIgnoresATooShortNeedle(): void
    {
        $block = $this->createBlock(36, 'text', ['content' => 'Notre CV et nos références']);

        $this->assertNull($this->createLocator()->locateLink($this->createPage($block), 'https://example.com/cv'));
    }

    public function testLocateLinkReturnsNullWhenNoBlockClaimsIt(): void
    {
        $page = $this->createPage($this->createBlock(11, 'text', ['content' => 'Nothing here']));

        $this->assertNull($this->createLocator()->locateLink($page, 'https://example.com/pages/old-offer/'));
    }
}
