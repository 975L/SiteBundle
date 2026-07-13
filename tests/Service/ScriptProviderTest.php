<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Service\ScriptProvider;
use PHPUnit\Framework\TestCase;

class ScriptProviderTest extends TestCase
{
    // The bundle's own front-end controllers.js is contributed as a front script
    public function testGetScriptsReturnsBundleFrontController(): void
    {
        $this->assertSame(['@c975l/site-bundle/controllers.js'], (new ScriptProvider())->getScripts());
    }

    // The bundle's own controllers-admin.js is contributed as an admin-only script
    public function testGetAdminScriptsReturnsBundleAdminController(): void
    {
        $this->assertSame(['@c975l/site-bundle/controllers-admin.js'], (new ScriptProvider())->getAdminScripts());
    }
}
