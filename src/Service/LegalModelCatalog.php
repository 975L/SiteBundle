<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

// The legal models the bundle ships, shared by the block's form (the "Model" choices), the renderer (which
// refuses any identifier not listed here, so nothing from the database can ever reach a template path) and
// the drift check. Adding a country means adding its templates under templates/models/{country}/ and its
// entry here - nothing else.
class LegalModelCatalog
{
    // Locale the models are authored in, served whenever the request locale has no template of its own
    public const FALLBACK_LOCALE = 'fr';

    // country label => [translation key => model identifier]
    private const MODELS = [
        'France' => [
            'label.cookies_policy' => 'france/cookies',
            'label.copyright' => 'france/copyright',
            'label.legal_notice' => 'france/legal-notice',
            'label.privacy_policy' => 'france/privacy-policy',
            'label.terms_of_sales' => 'france/terms-of-sales',
            'label.terms_of_use' => 'france/terms-of-use',
        ],
    ];

    // Grouped choices, straight into the block form's ChoiceType
    public function choices(): array
    {
        return self::MODELS;
    }

    // Every model identifier, flat
    public function all(): array
    {
        return array_merge(...array_map(array_values(...), array_values(self::MODELS)));
    }

    public function has(string $model): bool
    {
        return \in_array($model, $this->all(), true);
    }

    // Translation key labelling a model, or the identifier itself if it isn't one of ours
    public function label(string $model): string
    {
        foreach (self::MODELS as $models) {
            $key = array_search($model, $models, true);
            if (false !== $key) {
                return $key;
            }
        }

        return $model;
    }
}
