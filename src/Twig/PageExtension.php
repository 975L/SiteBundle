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
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class PageExtension extends AbstractExtension
{
    public function __construct(
        private readonly PageRepository $pageRepository
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('site_page', $this->getPage(...)),
            new TwigFunction('site_legal_pages', $this->getLegalPages(...)),
        ];
    }

    // Resolves a Page (with its blocks/medias eager-loaded), used by blocks referencing another page (e.g. articles_slider)
    public function getPage(?int $id): ?Page
    {
        return null !== $id ? $this->pageRepository->findOneByIdWithBlocks($id) : null;
    }

    // Resolves published pages matching given legal_model identifiers (e.g. 'france/cookies'), used to list related legal pages (e.g. Annexes section)
    public function getLegalPages(array $models): array
    {
        return $this->pageRepository->findByLegalModels($models);
    }
}
