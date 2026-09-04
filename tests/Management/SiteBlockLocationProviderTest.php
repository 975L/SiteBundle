<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Management\SiteBlockLocationProvider;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Tests\PagePublicUrlGeneratorTestTrait;
use c975L\UiBundle\Entity\Block;
use PHPUnit\Framework\TestCase;

class SiteBlockLocationProviderTest extends TestCase
{
    use PagePublicUrlGeneratorTestTrait;

    private function createProvider(array $pagesOwningBlocks, ?string $siteUrl = 'https://example.com'): SiteBlockLocationProvider
    {
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findByBlockIds')->willReturn($pagesOwningBlocks);

        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn($siteUrl);

        return new SiteBlockLocationProvider(
            $repository,
            new PagePublicUrlResolver($configService, $this->createUrlGenerator(), $this->createSiteLocales(), $this->createPageTranslator()),
        );
    }

    private function blockWithId(int $id): Block
    {
        $block = new Block();
        new \ReflectionProperty(Block::class, 'id')->setValue($block, $id);

        return $block;
    }

    private function createPage(string $slug, bool $published = true, bool $deleted = false): Page
    {
        $page = new Page();
        $page->setSlug($slug);
        $page->setTitle('Mentions légales');
        $page->setIsPublished($published);
        $page->setIsDeleted($deleted);

        return $page;
    }

    // What the "Legal models" screen shows in its first column, and the address its drift check tests
    public function testGetLocationsAnswersThePageTitleAndItsPublicUrl(): void
    {
        $block = $this->blockWithId(10);
        $page = $this->createPage('mentions-legales');
        $page->addBlock($block);

        $this->assertSame(
            [10 => ['label' => 'Mentions légales', 'url' => 'https://example.com/pages/mentions-legales', 'published' => true]],
            $this->createProvider([$page])->getLocations([$block]),
        );
    }

    // A draft legal page is still listed - it is the one being written - but has no public address yet
    public function testGetLocationsAnswersNoUrlForAnUnpublishedPage(): void
    {
        $block = $this->blockWithId(20);
        $page = $this->createPage('mentions-legales', false);
        $page->addBlock($block);

        $this->assertSame(
            [20 => ['label' => 'Mentions légales', 'url' => null, 'published' => false]],
            $this->createProvider([$page])->getLocations([$block]),
        );
    }

    // A page in the trash is not a place a visitor can read the document at either
    public function testGetLocationsAnswersNoUrlForADeletedPage(): void
    {
        $block = $this->blockWithId(30);
        $page = $this->createPage('mentions-legales', true, true);
        $page->addBlock($block);

        $this->assertSame(
            [30 => ['label' => 'Mentions légales', 'url' => null, 'published' => false]],
            $this->createProvider([$page])->getLocations([$block]),
        );
    }

    // A block no Page owns is simply not ours - the screen lists it with no location rather than dropping it
    public function testGetLocationsSkipsABlockNoPageOwns(): void
    {
        $this->assertSame([], $this->createProvider([])->getLocations([$this->blockWithId(40)]));
    }

    // Passing no blocks skips the repository query entirely
    public function testGetLocationsReturnsEmptyArrayForNoBlocks(): void
    {
        $this->assertSame([], $this->createProvider([])->getLocations([]));
    }
}
