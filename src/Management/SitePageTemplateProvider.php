<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management;

// Reads every config/page-templates/*.json shipped by SiteBundle into the page template catalog -
// each one is a reusable, ordered arrangement of blocks (kind + example data) an admin can apply to
// a Page (see PageCrudController::applyTemplate()), independent of the site's design (colors/fonts/
// shape), which stays controlled by ConfigBundle's theme presets (see SiteThemePresetProvider)
class SitePageTemplateProvider
{
    public function getTemplates(): array
    {
        $templates = [];

        foreach (glob(\dirname(__DIR__, 2) . '/config/page-templates/*.json') ?: [] as $file) {
            $id = basename($file, '.json');
            $data = json_decode(file_get_contents($file), true);

            $templates[$id] = [
                'label' => $data['label'],
                'blocks' => $data['blocks'],
            ];
        }

        return $templates;
    }

    public function getTemplate(string $id): ?array
    {
        return $this->getTemplates()[$id] ?? null;
    }
}
