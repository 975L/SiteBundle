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
use c975L\SiteBundle\Service\PageService;
use PHPUnit\Framework\TestCase;

// Lives under src/Tests (not a sibling tests/ dir) so it stays autoloadable by consuming apps,
// whose attribute route loader recursively reflects every class under the bundle root
class PageServiceTest extends TestCase
{
    // findAll() delegates to the repository's position-ordered finder
    public function testFindAllDelegatesToRepository(): void
    {
        $pages = [new Page(), new Page()];
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findAllOrdered')->willReturn($pages);

        $this->assertSame($pages, (new PageService($repository))->findAll());
    }

    // findOneBySlug() only resolves published, non-deleted pages
    public function testFindOneBySlugDelegatesToRepositoryAndCanReturnNull(): void
    {
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findOneBySlug')->willReturnCallback(
            static fn (string $slug): ?Page => 'home' === $slug ? new Page() : null
        );
        $service = new PageService($repository);

        $this->assertInstanceOf(Page::class, $service->findOneBySlug('home'));
        $this->assertNull($service->findOneBySlug('unknown'));
    }

    // findForDisplay() resolves a page regardless of its published/deleted status (redirects/410 handled by the caller)
    public function testFindForDisplayDelegatesToRepository(): void
    {
        $page = new Page();
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findOneBySlugForDisplay')->willReturn($page);

        $this->assertSame($page, (new PageService($repository))->findForDisplay('any-slug'));
    }
}
