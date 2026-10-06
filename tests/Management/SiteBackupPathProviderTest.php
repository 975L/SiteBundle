<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Management;

use c975L\ConfigBundle\Management\BackupPath;
use c975L\SiteBundle\Management\SiteBackupPathProvider;
use PHPUnit\Framework\TestCase;

class SiteBackupPathProviderTest extends TestCase
{
    // The public films and those only the back office shows, both mirrored: a film shot again replaces the one before
    public function testBothFilmFoldersAreMirrored(): void
    {
        $paths = [];
        foreach (new SiteBackupPathProvider()->getBackupPaths() as $path) {
            $paths[$path->path] = $path->mode;
        }

        $this->assertSame([
            'public/medias/films' => BackupPath::MODE_MIRROR,
            'private/medias/films' => BackupPath::MODE_MIRROR,
        ], $paths);
    }
}
