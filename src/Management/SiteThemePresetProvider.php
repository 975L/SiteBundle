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
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

// Reads every config/themes/*.json shipped by SiteBundle into the theme preset catalog (see readme)
class SiteThemePresetProvider implements ThemePresetProviderInterface
{
    public function __construct(
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public function getPresets(): array
    {
        $presets = [];

        foreach (glob(\dirname(__DIR__, 2) . '/config/themes/*.json') as $file) {
            $id = basename($file, '.json');
            $data = json_decode(file_get_contents($file), true);

            $presets[$id] = [
                'label' => $data['label'],
                // These presets are SiteBundle's own, translated in SiteBundle's own domain - ThemeCrudController (ConfigBundle) must never assume its own 'config' domain here
                'domain' => 'site',
                // Slug of the theme's shape stylesheet to activate with this preset (see StylesheetProvider) - the only thing a preset ever controls: colors/fonts stay entirely admin-owned (see ThemeCrudController::applyPreset()), a preset never overwrites them. Independent from page templates (see TemplateProviderInterface): a preset no longer references one, applying either never touches the other.
                'stylesheet' => $data['stylesheet'] ?? null,
                // A closure, not an eagerly-generated string: ThemePresetRegistry (ConfigBundle) is a constructor dependency of ThemeCrudController, which EasyAdmin instantiates just to enumerate its routes while the router is still building its own route collection - calling generate() eagerly here deadlocks that (Router::generate() needs the very collection this call chain is in the middle of assembling). ThemeCrudController's own ->linkToUrl() already accepts a callable (see its "apply preset" action, same file) and only invokes it while rendering the CRUD page, long after routes are actually ready.
                'previewUrl' => fn () => $this->urlGenerator->generate('page_preview', ['page' => 'home', 'preset' => $id]),
            ];
        }

        return $presets;
    }
}
