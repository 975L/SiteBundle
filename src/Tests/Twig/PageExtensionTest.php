<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Twig;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Twig\PageExtension;
use PHPUnit\Framework\TestCase;

// Lives under src/Tests (not a sibling tests/ dir) so it stays autoloadable by consuming apps,
// whose attribute route loader recursively reflects every class under the bundle root
class PageExtensionTest extends TestCase
{
    // A null id (e.g. an unset articles_slider block option) short-circuits without hitting the repository
    public function testGetPageReturnsNullWithoutQueryingRepositoryWhenIdIsNull(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects($this->never())->method('findOneByIdWithBlocks');

        $this->assertNull((new PageExtension($repository))->getPage(null));
    }

    // A given id delegates to the repository's eager-loading finder
    public function testGetPageDelegatesToRepositoryWhenIdIsGiven(): void
    {
        $page = new Page();
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findOneByIdWithBlocks')->willReturn($page);

        $this->assertSame($page, (new PageExtension($repository))->getPage(42));
    }

    // Legal pages are resolved by delegating the given legal_model identifiers to the repository
    public function testGetLegalPagesDelegatesToRepository(): void
    {
        $pages = [new Page()];
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findByLegalModels')->willReturn($pages);

        $this->assertSame($pages, (new PageExtension($repository))->getLegalPages(['france/cookies']));
    }
}
