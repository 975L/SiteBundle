<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\ExportProviderInterface;
use c975L\SiteBundle\Repository\MenuRepository;

// Serializes Menus (one row per location - see Menu::LOCATION_*, own Block collection) into the shape ContentExporter/MenuImportProvider expect, for the "export sync all" dashboard shortcut (see ConfigBundle's SyncAllExporter)
class MenuExportProvider implements ExportProviderInterface
{
    public function __construct(
        private readonly MenuRepository $menuRepository,
        private readonly BlockDataExporter $blockDataExporter,
    ) {
    }

    public function getKind(): string
    {
        return MenuImportProvider::KIND;
    }

    public function exportAll(): array
    {
        return $this->serialize($this->menuRepository->findAll());
    }

    // @param iterable<Menu> $menus
    public function serialize(iterable $menus): array
    {
        $files = [];
        $items = [];
        foreach ($menus as $menu) {
            $items[] = [
                'location' => $menu->getLocation(),
                'blocks' => $this->blockDataExporter->exportBlocks($menu->getBlocks(), $files),
            ];
        }

        return ['items' => $items, 'files' => $files];
    }
}
