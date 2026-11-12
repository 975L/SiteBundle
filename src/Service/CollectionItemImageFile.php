<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\ConfigBundle\Management\ArchivePath;
use c975L\UiBundle\Validator\AllowedUploadExtension;
use Vich\UploaderBundle\FileAbstraction\ReplacingFile;

// The image a collection item is imported with, named by a manifest or a JSON file nobody vetted (see CollectionItemImportProvider, CollectionItemImportCommand): a path leaving its folder, or a file whose extension is not an upload's, is ignored, and the source is never removed by Vich
final class CollectionItemImageFile
{
    // The file $relative names under $dir, ready for Vich, or null for anything else - SVG left out, an item's image being shown as a picture and an SVG running its scripts when opened directly
    public static function resolve(string $dir, mixed $relative): ?ReplacingFile
    {
        $path = ArchivePath::resolve($dir, $relative);
        $extension = null !== $path ? strtolower(pathinfo($path, \PATHINFO_EXTENSION)) : '';
        if ('svg' === $extension || !\in_array($extension, AllowedUploadExtension::EXTENSIONS, true)) {
            return null;
        }

        return new ReplacingFile($path, true, false, false);
    }
}
