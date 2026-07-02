<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Service;

use c975L\UiBundle\Contract\BundleScriptAdminProviderInterface;
use c975L\UiBundle\Contract\BundleScriptProviderInterface;

class ScriptProvider implements BundleScriptProviderInterface, BundleScriptAdminProviderInterface
{
    public function getScripts(): array
    {
        return [
            '@c975l/site-bundle/controllers.js',
        ];
    }

    public function getAdminScripts(): array
    {
        return [
            '@c975l/site-bundle/controllers-admin.js',
        ];
    }
}
