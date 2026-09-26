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
use c975L\SiteBundle\Service\PageEditUrlResolver;
use c975L\SiteBundle\Twig\PageExtension;
use PHPUnit\Framework\TestCase;

class PageExtensionTest extends TestCase
{
    private function extension(PageRepository $repository, ?PageEditUrlResolver $resolver = null): PageExtension
    {
        return new PageExtension($repository, $resolver ?? $this->createStub(PageEditUrlResolver::class));
    }

    // A content holder is meant to stay unpublished: the zone reads it whatever its status, through the same row-alone lookup as a page display
    public function testGetContentPageReturnsAnUnpublishedPage(): void
    {
        $page = new Page()->setSlug('shortcut-preview');
        $repository = $this->createMock(PageRepository::class);
        $repository->expects($this->once())->method('findOneBySlugForDisplay')->with('shortcut-preview')->willReturn($page);

        $this->assertSame($page, $this->extension($repository)->getContentPage('shortcut-preview'));
    }

    public function testGetContentPageReturnsNullWhenNoPageHasTheSlug(): void
    {
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findOneBySlugForDisplay')->willReturn(null);

        $this->assertNull($this->extension($repository)->getContentPage('shortcut-preview'));
    }

    public function testGetPageNewUrlDelegatesToTheResolver(): void
    {
        $resolver = $this->createMock(PageEditUrlResolver::class);
        $resolver->expects($this->once())->method('resolveNew')->with('shortcut-preview')->willReturn('/management/page/new?slug=shortcut-preview');

        $this->assertSame('/management/page/new?slug=shortcut-preview', $this->extension($this->createStub(PageRepository::class), $resolver)->getPageNewUrl('shortcut-preview'));
    }

    // A null id (e.g. an unset articles_slider block option) short-circuits without hitting the repository
    public function testGetPageReturnsNullWithoutQueryingRepositoryWhenIdIsNull(): void
    {
        $repository = $this->createMock(PageRepository::class);
        $repository->expects($this->never())->method('findOneByIdWithBlocks');

        $this->assertNull($this->extension($repository)->getPage(null));
    }

    // A given id delegates to the repository's eager-loading finder
    public function testGetPageDelegatesToRepositoryWhenIdIsGiven(): void
    {
        $page = new Page();
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findOneByIdWithBlocks')->willReturn($page);

        $this->assertSame($page, $this->extension($repository)->getPage(42));
    }

    // Legal pages are resolved by delegating the given legal_model identifiers to the repository
    public function testGetLegalPagesDelegatesToRepository(): void
    {
        $pages = [new Page()];
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findByLegalModels')->willReturn($pages);

        $this->assertSame($pages, $this->extension($repository)->getLegalPages(['france/cookies']));
    }

    // Links to the real Page carrying a "form" Block pointing at "register"/"reset_password_request", instead of the bare/generic route
    public function testGetPageForFormBlockDelegatesToRepository(): void
    {
        $page = new Page();
        $repository = $this->createMock(PageRepository::class);
        $repository->expects($this->once())->method('findOneByFormBlockName')->with('register')->willReturn($page);

        $this->assertSame($page, $this->extension($repository)->getPageForFormBlock('register'));
    }

    // No Page carries a "form" Block for that name (e.g. the admin removed it) - the caller falls back to the bare route
    public function testGetPageForFormBlockReturnsNullWhenNoPageCarriesIt(): void
    {
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findOneByFormBlockName')->willReturn(null);

        $this->assertNull($this->extension($repository)->getPageForFormBlock('reset_password_request'));
    }
}
