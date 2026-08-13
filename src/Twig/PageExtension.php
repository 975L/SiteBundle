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
use Twig\Attribute\AsTwigFunction;

class PageExtension
{
    public function __construct(
        private readonly PageRepository $pageRepository,
    ) {
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
