<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\ConfigBundle\Service\SiteUrlResolver;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PagePublicUrlResolver;
use c975L\SiteBundle\Service\PageSocialContentSource;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Entity\Media;
use PHPUnit\Framework\TestCase;

class PageSocialContentSourceTest extends TestCase
{
    private function createPage(int $id, string $creation, bool $indexable = true, bool $legal = false): Page
    {
        $page = new Page()->setTitle('Page ' . $id)->setSlug('page-' . $id)->setSummarySocialNetwork('<p>Résumé</p>')->setCreation(new \DateTime($creation))->setIsIndexable($indexable)->setIsPublished(true);
        $page->setOgImage(new Media()->setFilename('medias/site/media/page-' . $id . '.webp'));
        if ($legal) {
            $page->addBlock(new Block()->setKind('legal_model'));
        }
        new \ReflectionProperty(Page::class, 'id')->setValue($page, $id);

        return $page;
    }

    /**
     * @param list<Page> $pages
     */
    private function createSource(array $pages, ?string $siteUrl = 'https://example.org'): PageSocialContentSource
    {
        $repository = $this->createStub(PageRepository::class);
        $repository->method('findAllOrdered')->willReturn($pages);
        $repository->method('find')->willReturn($pages[0] ?? null);

        $urlResolver = $this->createStub(PagePublicUrlResolver::class);
        $urlResolver->method('resolve')->willReturnCallback(static fn (Page $page): ?string => null === $siteUrl ? null : $siteUrl . '/pages/' . $page->getSlug());

        $siteUrlResolver = $this->createStub(SiteUrlResolver::class);
        $siteUrlResolver->method('siteUrl')->willReturn($siteUrl);

        return new PageSocialContentSource($repository, $urlResolver, $siteUrlResolver, '/var/www/site');
    }

    public function testTheOldestPageNotPostedYetIsHandedOverWithItsSharingImage(): void
    {
        $content = $this->createSource([$this->createPage(1, '2026-03-01'), $this->createPage(2, '2026-01-01'), $this->createPage(3, '2026-02-01')])->getNextContent(['2']);

        $this->assertSame('3', $content?->sourceId);
        $this->assertSame('https://example.org/pages/page-3', $content->url);
        $this->assertSame('/var/www/site/public/medias/site/media/page-3.webp', $content->imagePath);
        $this->assertSame(['description' => 'Résumé'], $content->variables);
    }

    // An account form or the terms of sale have nothing to say to a follower
    public function testNeitherANonIndexablePageNorALegalOneIsOffered(): void
    {
        $this->assertNull($this->createSource([$this->createPage(1, '2026-01-01', indexable: false), $this->createPage(2, '2026-01-01', legal: true)])->getNextContent([]));
    }

    public function testAPageIsPostedOnce(): void
    {
        $this->assertNull($this->createSource([])->getRepeatAfterDays());
    }

    public function testAPageUnpublishedSinceIsNotReadAgain(): void
    {
        $page = $this->createPage(1, '2026-01-01');
        $this->assertSame('1', $this->createSource([$page])->getContent('1')?->sourceId);

        $page->setIsPublished(false);
        $this->assertNull($this->createSource([$page])->getContent('1'));
    }
}
