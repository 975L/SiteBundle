<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests;

use PHPUnit\Framework\TestCase;

// Guards scaffold/removed.json, the withdrawn scaffold files ConfigBundle's ScaffoldInstaller deletes from a site - it matches the site's file against the hashes declared here, so a malformed entry silently leaves the obsolete file in place
class ScaffoldRemovedJsonTest extends TestCase
{
    /**
     * @return array<string, array<int, string>>
     */
    private function loadRemoved(): array
    {
        $removed = json_decode((string) file_get_contents(__DIR__ . '/../scaffold/removed.json'), true, 512, \JSON_THROW_ON_ERROR);
        $this->assertIsArray($removed);
        $this->assertNotSame([], $removed);

        return $removed;
    }

    // A path still shipped by the scaffold would be copied back in the same run that deletes it
    public function testNoWithdrawnFileIsStillShipped(): void
    {
        foreach (array_keys($this->loadRemoved()) as $path) {
            $this->assertFileDoesNotExist(__DIR__ . '/../scaffold/' . $path, sprintf('"%s" is declared as withdrawn but the scaffold still ships it', $path));
        }
    }

    // Paths are relative to the project root, the form ScaffoldInstaller resolves them in
    public function testPathsAreRelative(): void
    {
        foreach (array_keys($this->loadRemoved()) as $path) {
            $this->assertStringStartsNotWith('/', $path);
            $this->assertStringNotContainsString('..', $path);
        }
    }

    // Each path carries the hashes of every version the scaffold ever delivered - anything but a lowercase sha256 matches no file, and the site keeps the obsolete one
    public function testEveryPathCarriesDistinctSha256Hashes(): void
    {
        foreach ($this->loadRemoved() as $path => $hashes) {
            $this->assertIsArray($hashes, sprintf('"%s" does not carry a list of hashes', $path));
            $this->assertNotSame([], $hashes, sprintf('"%s" carries no hash, so its file is never matched', $path));
            $this->assertSame(array_values(array_unique($hashes)), array_values($hashes), sprintf('"%s" declares the same hash twice', $path));

            foreach ($hashes as $hash) {
                $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', (string) $hash, sprintf('"%s" declares "%s", which is not a lowercase sha256', $path, $hash));
            }
        }
    }
}
