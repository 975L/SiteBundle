<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\ConfigBundle\Management\LinkableRouteRegistry;
use c975L\SiteBundle\Entity\Page;
use c975L\SiteBundle\Repository\PageRepository;
use c975L\UiBundle\Contract\LinkTargetProviderInterface;
use c975L\UiBundle\Service\BlockAnchorCollector;
use Symfony\Contracts\Translation\TranslatorInterface;

// Every "page:ID", "page:ID#anchor-blockId" and "route:NAME" a link can point at, as one flat list sorted by label - the target select of a menu link (see MenuLinkType) and the suggestions of a block's link field (see UiBundle's LinkTargetType) alike, both decoded back by MenuExtension::getMenuLinkUrl()
class LinkTargetChoices implements LinkTargetProviderInterface
{
    public function __construct(
        private readonly LinkableRouteRegistry $linkableRouteRegistry,
        private readonly PageRepository $pageRepository,
        private readonly TranslatorInterface $translator,
        private readonly BlockAnchorCollector $anchorCollector,
    ) {
    }

    public function linkTargets(): array
    {
        // Eager-joins blocks (and their nested slots, walked too - see BlockAnchorCollector) so building each page's anchor choices doesn't trigger one extra query per page/container (getBlocks()/getSlots() would otherwise lazy-load their collection on each access)
        $pages = $this->pageRepository->createQueryBuilder('p')
            ->leftJoin('p.blocks', 'b')
            ->addSelect('b')
            ->leftJoin('b.slots', 's')
            ->addSelect('s')
            ->andWhere('p.isDeleted = :deleted')
            ->setParameter('deleted', false)
            ->getQuery()
            ->getResult();

        $choices = [];
        foreach ($pages as $page) {
            // Unpublished pages stay pickable (editors need to wire links while still drafting a page) but are flagged: MenuExtension::getMenuLinkUrl() already resolves them to an empty URL until the page is published, so the entry just stays inert rather than ever breaking
            $pageLabel = $page->getTitle() . ($page->isPublished() ? '' : ' (' . $this->translator->trans('label.draft', [], 'site') . ')');
            $this->addChoice($choices, $pageLabel, 'page:' . $page->getId());
            $this->addAnchorChoices($choices, $page, $pageLabel);
        }

        // A contributed target is labelled by the registry itself - and by its picker label where it has one, an entry standing for one of a bundle's own rows saying what it is here ("Galerie - Paysages") among every page of the site, where the rendered menu item keeps that row's bare title
        foreach (array_keys($this->linkableRouteRegistry->all()) as $name) {
            $this->addChoice($choices, $this->linkableRouteRegistry->pickerLabel($name), 'route:' . $name);
        }

        ksort($choices, SORT_NATURAL | SORT_FLAG_CASE);

        return $choices;
    }

    // One flat entry per in-page anchor the page's blocks declare, those of a container's nested slots included (see UiBundle's BlockAnchorCollector) - no cascading/JS select needed, they sit in the same list as the pages themselves
    private function addAnchorChoices(array &$choices, Page $page, string $pageLabel): void
    {
        foreach ($this->anchorCollector->collect($page->getBlocks()) as $fragment => $sectionLabel) {
            $this->addChoice($choices, $pageLabel . ' → ' . $sectionLabel, 'page:' . $page->getId() . '#' . $fragment);
        }
    }

    // Choices are keyed by label - a second entry carrying the same one (two homonymous pages, a contributed route sharing a page's title...) would take the first's place and make it unpickable, so it gets numbered instead
    private function addChoice(array &$choices, string $label, string $value): void
    {
        $key = $label;
        for ($i = 2; isset($choices[$key]); ++$i) {
            $key = $label . ' (' . $i . ')';
        }

        $choices[$key] = $value;
    }
}
