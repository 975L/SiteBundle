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
use c975L\SiteBundle\Management\SiteMediaUsageProvider;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use Doctrine\ORM\Query;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Contracts\Translation\TranslatorInterface;

class SiteMediaUsageProviderTest extends TestCase
{
    // Builds a PageRepository double: findByBlockIds() answers $pagesOwningBlocks, and the createQueryBuilder()->...->getResult() chain (og-image lookup) answers $pagesWithOgImage
    private function createPageRepository(array $pagesOwningBlocks, array $pagesWithOgImage): PageRepository
    {
        $query = $this->createStub(Query::class);
        $query->method('getResult')->willReturn($pagesWithOgImage);

        $queryBuilder = $this->createStub(QueryBuilder::class);
        $queryBuilder->method('andWhere')->willReturnSelf();
        $queryBuilder->method('setParameter')->willReturnSelf();
        $queryBuilder->method('getQuery')->willReturn($query);

        $repository = $this->createStub(PageRepository::class);
        $repository->method('createQueryBuilder')->willReturn($queryBuilder);
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

    private function createTranslator(): TranslatorInterface
    {
        $translator = $this->createStub(TranslatorInterface::class);
        $translator->method('trans')->willReturnCallback(static fn (string $id, array $params = []): string => $id);

        return $translator;
    }

    private function createProvider(PageRepository $pageRepository): SiteMediaUsageProvider
    {
        return new SiteMediaUsageProvider($pageRepository, $this->createAdminUrlGenerator(), $this->createTranslator());
    }

    private function mediaWithId(int $id, ?string $role = null): Media
    {
        $media = new Media();
        $media->setRole($role);
        (new \ReflectionProperty(Media::class, 'id'))->setValue($media, $id);

        return $media;
    }

    // A site-wide graphic role (favicon, logo...) is reported as used, with a link to its own edit page
    public function testGetUsagesReportsSiteGraphicRole(): void
    {
        $media = $this->mediaWithId(1, Media::ROLE_FAVICON);
        $provider = $this->createProvider($this->createPageRepository([], []));

        $usages = $provider->getUsages([$media]);

        $this->assertSame('label.favicon', $usages[1][0]['label']);
        $this->assertSame('/admin/edit', $usages[1][0]['url']);
    }

    // A media without any role is not reported as a site-wide graphic
    public function testGetUsagesIgnoresMediaWithoutRole(): void
    {
        $media = $this->mediaWithId(2);
        $provider = $this->createProvider($this->createPageRepository([], []));

        $this->assertArrayNotHasKey(2, $provider->getUsages([$media]));
    }

    // A media attached to a Block owned by a Page is reported as used within that page's block
    public function testGetUsagesReportsMediaAttachedToPageBlock(): void
    {
        $block = new Block();
        (new \ReflectionProperty(Block::class, 'id'))->setValue($block, 10);

        $media = $this->mediaWithId(3);
        $media->setBlock($block);

        $page = new Page();
        $page->setTitle('About us');
        $page->addBlock($block);

        $provider = $this->createProvider($this->createPageRepository([$page], []));

        $usages = $provider->getUsages([$media]);

        $this->assertSame('label.media_used_in_page_block', $usages[3][0]['label']);
    }

    // A media set as a Page's own og-image override is reported as such
    public function testGetUsagesReportsMediaUsedAsPageOgImage(): void
    {
        $media = $this->mediaWithId(4);

        $page = new Page();
        $page->setTitle('Home');
        $page->setOgImage($media);

        $provider = $this->createProvider($this->createPageRepository([], [$page]));

        $usages = $provider->getUsages([$media]);

        $this->assertSame('label.media_used_as_og_image', $usages[4][0]['label']);
    }

    // A media used nowhere yields no entry at all
    public function testGetUsagesReturnsEmptyArrayForUnusedMedia(): void
    {
        $media = $this->mediaWithId(5);
        $provider = $this->createProvider($this->createPageRepository([], []));

        $this->assertSame([], $provider->getUsages([$media]));
    }
}
