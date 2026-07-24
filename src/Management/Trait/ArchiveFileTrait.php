<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management\Trait;

// Resolves a "/public/..."-relative $filename to its on-disk path and, if it exists, registers it into &$files (archive-relative path => disk path) for a Sync export zip - shared by every export provider bundling a real file (Font, CollectionItem, SiteGraphic, Block Media). Returns null when the file is missing/unreadable, so the caller can skip or degrade gracefully rather than exporting a broken reference
trait ArchiveFileTrait
{
    private function registerArchiveFile(string $projectDir, string $filename, array &$files): ?array
    {
        $path = $projectDir . '/public/' . $filename;
        if (!is_file($path)) {
            return null;
        }

        $archivePath = 'files/' . bin2hex(random_bytes(8)) . '_' . basename($filename);
        $files[$archivePath] = $path;

        return ['archivePath' => $archivePath, 'originalFilename' => basename($filename)];
    }
}
