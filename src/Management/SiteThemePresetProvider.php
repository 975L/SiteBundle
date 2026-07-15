<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\ThemePresetProviderInterface;

// Reads every config/themes/*.json shipped by SiteBundle into the theme preset catalog (see readme)
class SiteThemePresetProvider implements ThemePresetProviderInterface
{
    public function getPresets(): array
    {
        $presets = [];

        foreach (glob(\dirname(__DIR__, 2) . '/config/themes/*.json') as $file) {
            $id = basename($file, '.json');
            $data = json_decode(file_get_contents($file), true);

            $presets[$id] = [
                'label' => $data['label'],
                'values' => $data['values'],
                // Slug of the page-template stylesheet to activate with this preset (see
                // StylesheetProvider), null if this preset only carries colors/fonts
                'stylesheet' => $data['stylesheet'] ?? null,
                // Id of a config/page-templates/*.json (see SitePageTemplateProvider) whose blocks
                // demo this preset's look in ?preset=X preview only (PageController::preview()) -
                // applyPreset() never touches page content, so this never gets persisted either
                'pageTemplate' => $data['pageTemplate'] ?? null,
            ];
        }

        return $presets;
    }
}
