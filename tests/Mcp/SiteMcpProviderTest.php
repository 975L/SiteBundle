<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Mcp;

use c975L\ConfigBundle\Entity\Redirect;
use c975L\ConfigBundle\Repository\RedirectRepository;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\ConfigBundle\Service\SiteLocales;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Mcp\SiteCollectionTools;
use c975L\SiteBundle\Mcp\SiteMcpAccess;
use c975L\SiteBundle\Mcp\SiteMcpProvider;
use c975L\SiteBundle\Repository\CollectionGroupRepository;
use c975L\SiteBundle\Repository\CollectionItemRepository;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\CollectionItemSlugs;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\PageSlugs;
use c975L\SiteBundle\Service\PageTranslator;
use c975L\UiBundle\Service\BlockTextCollector;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\UnitOfWork;
use Mcp\Server\Exception\ToolCallException;
use PHPUnit\Framework\TestCase;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\Clock\MockClock;
use Symfony\Component\String\Slugger\AsciiSlugger;
use Symfony\Component\Validator\Validator\ValidatorInterface;

class SiteMcpProviderTest extends TestCase
{
    // The tools named under the "site" key, the collections' joined, the writing ones flagged, none destroying, a live edit said plainly
    public function testTools(): void
    {
        $tools = iterator_to_array($this->provider()->getMcpTools(), false);

        $this->assertSame('site', $this->provider()->getMcpKey());
        $this->assertSame(['site_page_list', 'site_page_get', 'site_page_create', 'site_page_edit', 'site_collection_list', 'site_collection_get', 'site_collection_item_create', 'site_collection_item_edit'], array_map(static fn ($tool): string => $tool->name, $tools));
        $this->assertSame([false, false, true, true, false, false, true, true], array_map(static fn ($tool): bool => $tool->writes, $tools));
        $this->assertSame([], array_filter(array_map(static fn ($tool): bool => $tool->destroys, $tools)));
        $this->assertStringContainsString('already published shows on the site at once', $this->provider()->getMcpInstructions());
    }

    // A draft added unpublished with a slug kept unique, members only when the site says so, an empty title refused
    public function testCreateDraft(): void
    {
        // Another page saved under the slug, an id of its own telling it from the draft
        $other = $this->createStub(Page::class);
        $other->method('getId')->willReturn(7);
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findOneBy')->willReturnCallback(static fn (array $criteria): ?Page => 'item-1' === $criteria['slug'] ? $other : null);
        $persisted = [];
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $this->assertSame('item-1-2', $this->provider($repository, $entityManager, membersOnly: true)->create('Item 1', ' description-1 ')['slug']);
        $this->assertInstanceOf(Page::class, $persisted[0]);
        $this->assertFalse($persisted[0]->isPublished());
        $this->assertTrue($persisted[0]->isMembersOnly());
        $this->assertSame('description-1', $persisted[0]->getSummarySocialNetwork());

        $this->expectException(ToolCallException::class);
        $this->provider($repository, $entityManager)->create(' ');
    }

    // Only the fields given change, a new title renaming the slug of a published page and redirecting its old url, as the back office does
    public function testEdit(): void
    {
        $page = new Page()->setTitle('item-1')->setSlug('item-1')->setSummarySocialNetwork('description-1')->setIsPublished(true);
        $repository = $this->createStub(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn(['slug' => 'item-1', 'title' => 'item-1', 'isPublished' => true]);
        $persisted = [];
        $entityManager = $this->createStub(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);
        $entityManager->method('persist')->willReturnCallback(static function (object $entity) use (&$persisted): void {
            $persisted[] = $entity;
        });

        $this->assertSame('item-2', $this->provider($repository, $entityManager)->edit(1, title: 'Item 2')['slug']);
        $this->assertSame(['Item 2', 'description-1'], [$page->getTitle(), $page->getSummarySocialNetwork()]);
        $this->assertInstanceOf(Redirect::class, $persisted[0]);
        $this->assertSame(['/pages/item-1', '/pages/item-2'], [$persisted[0]->getFromPath(), $persisted[0]->getToUrl()]);

        $this->provider($repository, $entityManager)->edit(1, description: '');
        $this->assertNull($page->getSummarySocialNetwork());
    }

    // A new title giving no slug is refused, nothing written
    public function testEditRefusesTitleWithoutSlug(): void
    {
        $page = new Page()->setTitle('item-1')->setSlug('item-1');
        $repository = $this->createStub(PageRepository::class);
        $repository->method('find')->willReturn($page);
        $unitOfWork = $this->createStub(UnitOfWork::class);
        $unitOfWork->method('getOriginalEntityData')->willReturn(['slug' => 'item-1', 'title' => 'item-1', 'isPublished' => false]);
        $entityManager = $this->createMock(EntityManagerInterface::class);
        $entityManager->method('getUnitOfWork')->willReturn($unitOfWork);
        $entityManager->expects($this->never())->method('flush');

        $this->expectException(ToolCallException::class);
        $this->provider($repository, $entityManager)->edit(1, title: '!!!');
    }

    // A page read with its description, no url while unpublished, and the texts of its blocks
    public function testGet(): void
    {
        $page = new Page()->setTitle('item-1')->setSlug('item-1')->setSummarySocialNetwork('description-1');
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findOneByIdWithBlocks')->willReturn($page);
        $collector = $this->createStub(BlockTextCollector::class);
        $collector->method('collect')->willReturn([['owner' => 'block', 'ownerId' => 1, 'field' => 'text', 'source' => 'text-1', 'label' => 'item-1 / catalog']]);

        $read = $this->provider($repository, collector: $collector)->get(1);
        $this->assertSame(['description-1', null], [$read['description'], $read['url']]);
        $this->assertSame([['block' => 'item-1 / catalog', 'field' => 'text', 'text' => 'text-1']], $read['blocks']);
    }

    // A page in the trash answers as unknown
    public function testTrashedPageUnknown(): void
    {
        $repository = $this->createStub(PageRepository::class);
        $repository->method('find')->willReturn(new Page()->setIsDeleted(true));

        $this->expectException(ToolCallException::class);
        $this->provider($repository)->edit(1, title: 'item-2');
    }

    // An account that does not edit the pages is refused every tool
    public function testRefusedWithoutTheRole(): void
    {
        $this->expectException(ToolCallException::class);
        $this->provider(granted: false)->list();
    }

    // The provider for an account editing the pages or not, on a site whose new pages are kept for members or not
    private function provider(?PageRepository $repository = null, ?EntityManagerInterface $entityManager = null, bool $granted = true, bool $membersOnly = false, ?BlockTextCollector $collector = null): SiteMcpProvider
    {
        $repository ??= $this->createStub(PageRepository::class);
        $config = $this->createStub(ConfigServiceInterface::class);
        $config->method('get')->willReturn('ROLE_EDITOR');
        $config->method('getBool')->willReturn($membersOnly);
        $security = $this->createStub(Security::class);
        $security->method('isGranted')->willReturn($granted);

        $access = new SiteMcpAccess($config, $security);

        return new SiteMcpProvider(
            $repository,
            $entityManager ?? $this->createStub(EntityManagerInterface::class),
            $config,
            $access,
            new SiteCollectionTools(
                $this->createStub(CollectionGroupRepository::class),
                $this->createStub(CollectionItemRepository::class),
                $this->createStub(CollectionItemSlugs::class),
                $this->createStub(EntityManagerInterface::class),
                $this->createStub(ValidatorInterface::class),
                $access,
            ),
            new MockClock('2026-11-21 10:00'),
            new PageSlugs($repository, $this->createStub(RedirectRepository::class), new AsciiSlugger(), new SiteLocales([], 'fr'), $this->createStub(PageTranslator::class)),
            $this->createStub(PagePublicUrlResolver::class),
            $collector ?? $this->createStub(BlockTextCollector::class),
        );
    }
}
