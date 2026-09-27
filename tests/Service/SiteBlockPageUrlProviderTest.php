<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\SiteBlockPageUrlProvider;
use c975L\UiBundle\Entity\Block;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SiteBlockPageUrlProviderTest extends TestCase
{
    // The block's own anchor, as BlockExtension renders it, so the link lands on the section rather than the page's top
    public function testPointsAtTheBlockWhenItHasAnAnchor(): void
    {
        $this->assertSame('/page_display/tarifs#credits-27', $this->createProvider($this->page(['anchor' => 'credits']))->getBlockPageUrl('purchasecredits_packs'));
    }

    // A block saved with no anchor is reached through its page alone
    public function testPointsAtThePageWhenTheBlockHasNoAnchor(): void
    {
        $this->assertSame('/page_display/tarifs', $this->createProvider($this->page([]))->getBlockPageUrl('purchasecredits_packs'));
    }

    // No published page carries that kind: null, the caller linking nowhere
    public function testReturnsNullWhenNoPageCarriesTheKind(): void
    {
        $this->assertNull($this->createProvider(null)->getBlockPageUrl('purchasecredits_packs'));
    }

    // The "tarifs" page carrying block 27 of that kind, with that data
    private function page(array $data): Page
    {
        $block = new Block()->setKind('purchasecredits_packs')->setData($data);
        new \ReflectionProperty(Block::class, 'id')->setValue($block, 27);

        return new Page()->setTitle('Tarifs')->setSlug('tarifs')->addBlock($block);
    }

    // Over a repository answering $page for any kind, and a url generator echoing the slug it was given
    private function createProvider(?Page $page): SiteBlockPageUrlProvider
    {
        $pageRepository = $this->createStub(PageRepository::class);
        $pageRepository->method('findOneByBlockKind')->willReturn($page);

        $urlGenerator = $this->createStub(UrlGeneratorInterface::class);
        $urlGenerator->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters = []): string => '/' . $route . '/' . ($parameters['page'] ?? '')
        );

        return new SiteBlockPageUrlProvider($pageRepository, $urlGenerator);
    }
}
