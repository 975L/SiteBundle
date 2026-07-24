<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\ImportProviderInterface;
use c975L\SiteBundle\Entity\Menu;
use c975L\SiteBundle\Repository\MenuRepository;
use Doctrine\ORM\EntityManagerInterface;

// Imports a "site_menu" content export (see MenuExportProvider) - matches by location, Menu's own unique constraint (one row per location, see Menu::LOCATION_*). Existing Blocks have no natural key of their own, so the whole collection is replaced, same as PageImportProvider does for a Page
class MenuImportProvider implements ImportProviderInterface
{
    public const KIND = 'site_menu';

    public function __construct(
        private readonly EntityManagerInterface $em,
        private readonly MenuRepository $menuRepository,
        private readonly BlockDataImporter $blockDataImporter,
    ) {
    }

    public function supportsImport(string $kind): bool
    {
        return self::KIND === $kind;
    }

    public function import(array $items, ?string $filesDir = null): array
    {
        $created = 0;
        $updated = 0;

        foreach ($items as $item) {
            $menu = $this->menuRepository->findOneByLocation($item['location']);
            $isNew = null === $menu;
            $menu ??= (new Menu())->setLocation($item['location']);

            foreach ($menu->getBlocks()->toArray() as $existingBlock) {
                $menu->removeBlock($existingBlock);
            }

            foreach ($this->blockDataImporter->buildBlocks($item['blocks'] ?? [], $filesDir) as $block) {
                $menu->addBlock($block);
            }

            $this->em->persist($menu);
            $isNew ? $created++ : $updated++;
        }

        $this->em->flush();

        return ['created' => $created, 'updated' => $updated];
    }
}
