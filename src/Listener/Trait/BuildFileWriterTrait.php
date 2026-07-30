<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Listener\Trait;

// The one way this bundle's listeners drop a generated stylesheet into public/bundles/build/ - shared by ThemeVariablesCssListener and FontCssListener, which both rewrite their whole file on every save. Using classes rather than a service because both are Doctrine listeners already holding kernel.project_dir
trait BuildFileWriterTrait
{
    // Written to a temporary file then renamed, so a request reading the file while it's being rewritten never sees a half-written stylesheet - rename() is atomic on the same filesystem
    private function writeBuildFile(string $filename, string $contents): void
    {
        $buildDir = $this->projectDir . '/public/bundles/build';
        if (!is_dir($buildDir) && !@mkdir($buildDir, 0775, true) && !is_dir($buildDir)) {
            throw new \RuntimeException(sprintf('Unable to create the "%s" directory.', $buildDir));
        }

        $path = $buildDir . '/' . $filename;
        $tmpPath = $path . '.' . uniqid('', true) . '.tmp';
        if (false === @file_put_contents($tmpPath, $contents) || !@rename($tmpPath, $path)) {
            throw new \RuntimeException(sprintf('Unable to write "%s".', $path));
        }
    }
}
