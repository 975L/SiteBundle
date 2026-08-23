<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\SiteBundle\Controller\Management\PageCrudController;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Contract\BlockEditUrlProviderInterface;
use c975L\UiBundle\Entity\Block;
use c975L\UiBundle\Repository\FormRepository;
use c975L\UiBundle\Service\BlockFocusUrl;
use c975L\UiBundle\Service\FormEditUrl;
use c975L\UiBundle\Service\LegalModelCatalog;
use c975L\UiBundle\Service\LegalModelEditUrl;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Resolves, for UiBundle's front-end "Edit this block" hover button, the EasyAdmin edit URL of the Page owning a given Block
class SiteBlockEditUrlProvider implements BlockEditUrlProviderInterface
{
    public function __construct(
        private readonly PageRepository $pageRepository,
        private readonly AdminUrlGeneratorInterface $adminUrlGenerator,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly LegalModelCatalog $catalog,
        private readonly FormRepository $formRepository,
    ) {
    }

    public function getEditUrls(array $blocks): array
    {
        $blockIds = array_filter(array_map(static fn (Block $block): ?int => $block->getId(), $blocks));

        if ([] === $blockIds) {
            return [];
        }

        $urls = [];
        foreach ($this->pageRepository->findByBlockIds($blockIds) as $page) {
            foreach ($page->getBlocks() as $block) {
                if (\in_array($block->getId(), $blockIds, true)) {
                    $urls[$block->getId()] = $this->editUrl($page, $block);
                }
            }
        }

        return $urls;
    }

    // Two kinds are edited somewhere other than the Page form that carries them, and UiBundle answers where: a legal_model on its wording screen, a form on the Form's own, where its fields are. Anything else - and either of those pointing at something that no longer exists, which would 404 - keeps the Page's form
    private function editUrl(Page $page, Block $block): string
    {
        return FormEditUrl::build($this->adminUrlGenerator, $this->formRepository, $block)
            ?? LegalModelEditUrl::build($this->urlGenerator, $this->catalog, $block)
            ?? BlockFocusUrl::build($this->adminUrlGenerator, PageCrudController::class, $page->getId(), $block);
    }
}
