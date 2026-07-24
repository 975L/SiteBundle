<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\SiteBundle\Management\ImportmapProvider;
use PHPUnit\Framework\TestCase;

class ImportmapProviderTest extends TestCase
{
    public function testGetAdminImportmapEntriesReturnsControllersAdminEntrypoint(): void
    {
        $entries = (new ImportmapProvider())->getAdminImportmapEntries();

        $this->assertSame([
            '@c975l/site-bundle/controllers-admin.js' => [
                'path' => './vendor/c975l/site-bundle/assets/controllers-admin.js',
                'entrypoint' => true,
            ],
        ], $entries);
    }

    public function testGetImportmapEntriesReturnsControllersEntrypoint(): void
    {
        $entries = (new ImportmapProvider())->getImportmapEntries();

        $this->assertSame([
            '@c975l/site-bundle/controllers.js' => [
                'path' => './vendor/c975l/site-bundle/assets/controllers.js',
                'entrypoint' => true,
            ],
        ], $entries);
    }
}
