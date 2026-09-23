<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\ConfigBundle\Service\SiteUrlResolver;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Contract\SocialContentSourceInterface;
use c975L\UiBundle\Model\SocialContent;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Hands SocialBundle's publication the site's pages, oldest first - a site without SocialBundle simply never asks. What went out where is SocialBundle's to record
class PageSocialContentSource implements SocialContentSourceInterface
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PagePublicUrlResolver $pagePublicUrlResolver,
        private readonly SiteUrlResolver $siteUrlResolver,
        #[Autowire(param: 'kernel.project_dir')]
        private readonly string $projectDir,
    ) {
    }

    public function getSourceType(): string
    {
        return 'page';
    }

    // Never: a page is posted once, what is worth announcing again being new content with a page of its own
    public function getRepeatAfterDays(): ?int
    {
        return null;
    }

    public function getNextContent(array $excludedIds): ?SocialContent
    {
        $pages = array_filter(
            $this->pageRepository->findAllOrdered(),
            fn (Page $page): bool => !\in_array((string) $page->getId(), $excludedIds, true) && $this->isPostable($page),
        );
        usort($pages, static fn (Page $a, Page $b): int => $a->getCreation() <=> $b->getCreation());

        return [] === $pages ? null : $this->toContent($pages[0]);
    }

    // Null for a page unpublished or trashed since its post was prepared
    public function getContent(string $sourceId): ?SocialContent
    {
        $page = $this->pageRepository->find((int) $sourceId);

        return $page instanceof Page && $page->isPublished() && !$page->isDeleted() && $this->isPostable($page) ? $this->toContent($page) : null;
    }

    // What a search engine is told to list, minus the legal notices: a page with nothing to say to a reader (an account form, the terms of sale) has nothing to say to a follower either
    private function isPostable(Page $page): bool
    {
        if (!$page->isIndexable()) {
            return false;
        }

        foreach ($page->getBlocks() as $block) {
            if ('legal_model' === $block->getKind()) {
                return false;
            }
        }

        return true;
    }

    // Null while "site-url" is unset: the run happens in a console, with no request to take the host from
    private function toContent(Page $page): ?SocialContent
    {
        $url = $this->pagePublicUrlResolver->resolve($page);
        if (null === $url) {
            return null;
        }

        // The page's own sharing image; without one the post goes out as text, the site-wide default image saying nothing of this page
        $image = $page->getOgImage();
        $filename = $image?->getFilename();

        return new SocialContent(
            sourceId: (string) $page->getId(),
            title: (string) $page->getTitle(),
            url: $url,
            imagePath: null === $filename ? null : $this->projectDir . '/public/' . $filename,
            imageUrl: null === $filename ? null : $this->siteUrlResolver->siteUrl() . '/' . $filename,
            imageAlt: $image?->getAlt() ?? (string) $page->getTitle(),
            variables: array_filter(['description' => trim(html_entity_decode(strip_tags((string) $page->getSummarySocialNetwork())))]),
        );
    }
}
