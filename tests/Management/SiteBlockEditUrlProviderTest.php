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
use c975L\UiBundle\Controller\Management\LegalModelController;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Service\LegalModelCatalog;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class SiteBlockEditUrlProviderTest extends TestCase
{
    private function createProvider(array $pagesOwningBlocks): SiteBlockEditUrlProvider
    {
        return new SiteBlockEditUrlProvider(
            $this->createPageRepository($pagesOwningBlocks),
            $this->createAdminUrlGenerator(),
            $this->createUrlGenerator(),
            new LegalModelCatalog(),
        );
    }

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

    private function createUrlGenerator(): UrlGeneratorInterface
    {
        $generator = $this->createStub(UrlGeneratorInterface::class);
        $generator->method('generate')->willReturnCallback(
            static fn (string $route, array $parameters = []): string => '/admin/' . $route . '/' . ($parameters['block'] ?? '')
        );

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

        $provider = $this->createProvider([$page]);

        $this->assertSame([10 => '/admin/edit'], $provider->getEditUrls([$block]));
    }

    // A block with no owning Page (not found by findByBlockIds) resolves to nothing - no error
    public function testGetEditUrlsReturnsEmptyArrayForUnownedBlock(): void
    {
        $block = $this->blockWithId(20);

        $provider = $this->createProvider([]);

        $this->assertSame([], $provider->getEditUrls([$block]));
    }

    // Passing no blocks skips the repository query entirely
    public function testGetEditUrlsReturnsEmptyArrayForNoBlocks(): void
    {
        $provider = $this->createProvider([]);

        $this->assertSame([], $provider->getEditUrls([]));
    }

    // A legal_model block points at its own customization screen, where its wording is edited, not at its row in the Page's form
    public function testGetEditUrlsResolvesCustomizeUrlForLegalModelBlock(): void
    {
        $block = $this->blockWithId(30);
        $block->setKind('legal_model');
        $block->setData(['model' => 'france/legal-notice']);

        $page = new Page();
        $page->addBlock($block);

        $provider = $this->createProvider([$page]);

        $this->assertSame(
            [30 => '/admin/' . LegalModelController::CUSTOMIZE_ROUTE . '/30'],
            $provider->getEditUrls([$block])
        );
    }

    // A model no longer shipped by the bundle would 404 on the customization screen, so the Page's form stays the way in
    public function testGetEditUrlsFallsBackToPageFormForUnknownLegalModel(): void
    {
        $block = $this->blockWithId(40);
        $block->setKind('legal_model');
        $block->setData(['model' => 'atlantis/legal-notice']);

        $page = new Page();
        $page->addBlock($block);

        $provider = $this->createProvider([$page]);

        $this->assertSame([40 => '/admin/edit'], $provider->getEditUrls([$block]));
    }
}
