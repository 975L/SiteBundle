<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Twig;

use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\SiteBundle\Service\PageEditUrlResolver;
use Twig\Attribute\AsTwigFunction;

class PageExtension
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly PageEditUrlResolver $pageEditUrlResolver,
    ) {
    }

    // The Page whose blocks a screen of the app's own shows (see components/Page/Blocks.html.twig), published or not - a content holder is meant to stay unpublished, its url answering 404. The row alone, as for a page display: render_owned_blocks() reads the blocks on a cache miss only. A trashed page is returned too, for the zone to render nothing rather than offer to create a page whose slug it still holds - restoring it brings the zone back
    #[AsTwigFunction('site_content_page')]
    public function getContentPage(string $slug): ?Page
    {
        return $this->pageRepository->findOneBySlugForDisplay($slug);
    }

    // The back-office screen creating the Page a content zone reads, its slug prefilled
    #[AsTwigFunction('site_page_new_url')]
    public function getPageNewUrl(string $slug): string
    {
        return $this->pageEditUrlResolver->resolveNew($slug);
    }

    // Resolves a Page (with its blocks/medias eager-loaded), used by blocks referencing another page (e.g. articles_slider)
    #[AsTwigFunction('site_page')]
    public function getPage(?int $id): ?Page
    {
        return null !== $id ? $this->pageRepository->findOneByIdWithBlocks($id) : null;
    }

    // Resolves the published Page carrying a "form" Block pointing at the given Form name (e.g. "register") - used to link a generic/bare route's own cross-references to the real Page instead, see PageRepository::findOneByFormBlockName()
    #[AsTwigFunction('site_page_for_form_block')]
    public function getPageForFormBlock(string $formName): ?Page
    {
        return $this->pageRepository->findOneByFormBlockName($formName);
    }

    // Resolves published pages matching given legal_model identifiers (e.g. 'france/cookies'), used to list related legal pages (e.g. Annexes section)
    #[AsTwigFunction('site_legal_pages')]
    public function getLegalPages(array $models): array
    {
        return $this->pageRepository->findByLegalModels($models);
    }
}
