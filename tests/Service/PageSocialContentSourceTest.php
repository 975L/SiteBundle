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
use c975L\UiBundle\Model\SocialContent;
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
    private function createSource(array $pages, ?string $siteUrl = 'https://example.org', ?string $unreachableSlug = null): PageSocialContentSource
    {
        // What findSocialCandidates() does in SQL: the pages not excluded, oldest first, the id telling apart those created together
        $candidates = static function (array $excludedIds) use ($pages): array {
            $free = array_values(array_filter($pages, static fn (Page $page): bool => !\in_array((string) $page->getId(), $excludedIds, true)));
            usort($free, static fn (Page $a, Page $b): int => [$a->getCreation(), $a->getId()] <=> [$b->getCreation(), $b->getId()]);

            return $free;
        };

        $repository = $this->createStub(PageRepository::class);
        $repository->method('findSocialCandidates')->willReturnCallback($candidates);
        $repository->method('find')->willReturn($pages[0] ?? null);

        $urlResolver = $this->createStub(PagePublicUrlResolver::class);
        $urlResolver->method('resolve')->willReturnCallback(static fn (Page $page): ?string => null === $siteUrl || $unreachableSlug === $page->getSlug() ? null : $siteUrl . '/pages/' . $page->getSlug());

        $siteUrlResolver = $this->createStub(SiteUrlResolver::class);
        $siteUrlResolver->method('siteUrl')->willReturn($siteUrl);

        return new PageSocialContentSource($repository, $urlResolver, $siteUrlResolver, '/var/www/site');
    }

    /**
     * @param list<SocialContent> $contents
     *
     * @return list<string>
     */
    private function sourceIds(array $contents): array
    {
        return array_map(static fn (SocialContent $content): string => $content->sourceId, $contents);
    }

    public function testTheOldestPageNotPostedYetIsHandedOverWithItsSharingImage(): void
    {
        $content = $this->createSource([$this->createPage(1, '2026-03-01'), $this->createPage(2, '2026-01-01'), $this->createPage(3, '2026-02-01')])->getNextContent(['2']);

        $this->assertSame('3', $content?->sourceId);
        $this->assertSame('https://example.org/pages/page-3', $content->url);
        $this->assertSame('/var/www/site/public/medias/site/media/page-3.webp', $content->imagePath);
        $this->assertSame(['description' => 'Résumé'], $content->variables);
    }

    // A page without a public url is passed over for the next one, not left blocking the publication
    public function testAPageWithoutAPublicUrlIsPassedOver(): void
    {
        $source = $this->createSource([$this->createPage(1, '2026-01-01'), $this->createPage(2, '2026-02-01')], unreachableSlug: 'page-1');

        $this->assertSame('2', $source->getNextContent([])?->sourceId);
    }

    // Two pages created together come out in the order of their ids
    public function testPagesCreatedTogetherAreToldApartByTheirId(): void
    {
        $this->assertSame('2', $this->createSource([$this->createPage(5, '2026-01-01'), $this->createPage(2, '2026-01-01')])->getNextContent([])?->sourceId);
    }

    // An account form or the terms of sale have nothing to say to a follower - the rule the candidates' query applies in SQL, checked here on a post already prepared
    public function testNeitherANonIndexablePageNorALegalOneIsOffered(): void
    {
        $this->assertNull($this->createSource([$this->createPage(1, '2026-01-01', indexable: false)])->getContent('1'));
        $this->assertNull($this->createSource([$this->createPage(2, '2026-01-01', legal: true)])->getContent('2'));
    }

    // A page kept for members has nothing to say to a follower either, who could not read it
    public function testAPageKeptForMembersIsNotOffered(): void
    {
        $this->assertNull($this->createSource([$this->createPage(1, '2026-01-01')->setIsMembersOnly(true)])->getContent('1'));
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

    // What a post's page is chosen among: the pages still free, the latest first, up to the limit - the scopes ignored, pages having none
    public function testTheContentsToChooseAreTheFreePagesLatestFirst(): void
    {
        $source = $this->createSource([$this->createPage(1, '2026-03-01'), $this->createPage(2, '2026-01-01'), $this->createPage(3, '2026-02-01')]);

        $this->assertSame(['1', '2'], $this->sourceIds($source->findContents(['3'], ['9'], 48)));
        $this->assertSame(['1'], $this->sourceIds($source->findContents([], [], 1)));
    }

    // A page without a public url never takes one of the limited places
    public function testThePagesWithoutAPublicUrlAreLeftOutBeforeTheLimit(): void
    {
        $source = $this->createSource([$this->createPage(1, '2026-01-01'), $this->createPage(2, '2026-02-01')], unreachableSlug: 'page-2');

        $this->assertSame(['1'], $this->sourceIds($source->findContents([], [], 1)));
    }

    // Pages are not split into groups, so a page has no scope to be drawn again from
    public function testAPageHasNoScope(): void
    {
        $this->assertNull($this->createSource([$this->createPage(1, '2026-01-01')])->getContentScope('1'));
    }
}
