<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\SiteBundle\Entity\CollectionItem;
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Entity\Page;
use c975L\UiBundle\Contract\TranslatableTextProviderInterface;
use c975L\UiBundle\Service\BlockTextCollector;
use Doctrine\ORM\EntityManagerInterface;

// What this bundle has the site say, handed to "c975l:translate:content": each page's own two texts and its blocks, the menus, and the collection items' cards
class SiteTextProvider implements TranslatableTextProviderInterface
{
    public function __construct(
        private readonly EntityManagerInterface $manager,
        private readonly BlockTextCollector $blockTextCollector,
    ) {
    }

    // Pages first, then menus, then collection items, a deleted page left out
    public function getTranslatableTexts(): iterable
    {
        $rows = [];

        foreach ($this->manager->getRepository(Page::class)->findBy(['isDeleted' => false], ['slug' => 'ASC']) as $page) {
            $id = $page->getId();
            if (null === $id) {
                continue;
            }

            $label = 'Page ' . $page->getSlug();

            foreach (['title' => $page->getTitle(), 'summarySocialNetwork' => $page->getSummarySocialNetwork()] as $field => $source) {
                if (\is_string($source) && '' !== trim($source)) {
                    $rows[] = ['owner' => PageTranslator::OWNER, 'ownerId' => $id, 'field' => $field, 'source' => $source, 'label' => $label];
                }
            }

            array_push($rows, ...$this->blockTextCollector->collect($page->getBlocks(), $label));
        }

        // A menu is a list of labels and nothing else, and it is read on every page: a site left with French menus is a site that reads French, whatever its pages say
        foreach ($this->manager->getRepository(Menu::class)->findAll() as $menu) {
            array_push($rows, ...$this->blockTextCollector->collect($menu->getBlocks(), 'Menu ' . $menu->getLocation()));
        }

        foreach ($this->manager->getRepository(CollectionItem::class)->findAll() as $item) {
            $this->collectionItemRows($item, $rows);
        }

        return $rows;
    }

    // The card an item prints, its image, link and place being the same in every language (see CollectionItemTranslator)
    /** @param list<array{owner: string, ownerId: int, field: string, source: string, label: string}> $rows */
    private function collectionItemRows(CollectionItem $item, array &$rows): void
    {
        $id = $item->getId();
        if (null === $id) {
            return;
        }

        $label = 'Collection ' . $item->getCollectionGroup()?->getName();

        foreach (['title' => $item->getTitle(), 'description' => $item->getDescription()] as $field => $source) {
            if (\is_string($source) && '' !== trim($source)) {
                $rows[] = ['owner' => CollectionItemTranslator::OWNER, 'ownerId' => $id, 'field' => $field, 'source' => $source, 'label' => $label];
            }
        }
    }
}
