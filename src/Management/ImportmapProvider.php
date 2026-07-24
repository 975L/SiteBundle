<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\ImportmapProviderInterface;

// Same import names as ScriptProvider (BundleScriptAdminProviderInterface/BundleScriptProviderInterface) - that one tells the dashboard/front layout which scripts to load, this one tells c975l:config:check-importmap what importmap.php entry each one needs
class ImportmapProvider implements ImportmapProviderInterface
{
    public function getAdminImportmapEntries(): array
    {
        return [
            '@c975l/site-bundle/controllers-admin.js' => [
                'path' => './vendor/c975l/site-bundle/assets/controllers-admin.js',
                'entrypoint' => true,
            ],
        ];
    }

    public function getImportmapEntries(): array
    {
        return [
            '@c975l/site-bundle/controllers.js' => [
                'path' => './vendor/c975l/site-bundle/assets/controllers.js',
                'entrypoint' => true,
            ],
        ];
    }
}
