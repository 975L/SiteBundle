<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\UiBundle\Contract\BundleStylesheetProviderInterface;

class StylesheetProvider implements BundleStylesheetProviderInterface
{
    public function getStylesheets(): array
    {
        return [
            'bundles/c975lsite/css/styles.min.css',
        ];
        // The compiled theme variables (bundles/build/site-theme.css) are contributed by UiBundle's own ThemeVariablesStylesheetProvider, alongside the listener that writes them - loaded after every bundle's sheet, this one included, so the admin's values win the cascade
        // Nothing else: a site's own design tokens live in its assets/styles/themes/*.css, one file per installed c975L bundle, copied once by the scaffold and owned by the app from then on (see readme "Themes"). They are contributed by the app's own App\Service\ThemeStylesheetProvider, also scaffolded - not from here, which would have this bundle name files belonging to the site and to bundles it knows nothing about
    }
}
