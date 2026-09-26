<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\BackupPath;
use c975L\ConfigBundle\Management\BackupPathProviderInterface;
use c975L\SiteBundle\Service\TutorialCatalog;

// The tutorial films this bundle shows, published as files and never pointed at by any row: a database dump cannot bring them back, and ConfigBundle backs up nothing it was not declared
class SiteBackupPathProvider implements BackupPathProviderInterface
{
    public function getBackupPaths(): array
    {
        return [
            // Mirrored rather than archived: a film shot again replaces the one before, and bzip2 gains nothing on a webm
            new BackupPath('public/' . TutorialCatalog::DIRECTORY, BackupPath::MODE_MIRROR),
        ];
    }
}
