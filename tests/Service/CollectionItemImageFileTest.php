<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Service\CollectionItemImageFile;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;

// What an import may hand Vich for a collection item: a file of its own folder, with an upload's extension, never removed afterwards
class CollectionItemImageFileTest extends TestCase
{
    private string $dir;

    // A folder holding one file per extension tried, and a secret beside it
    protected function setUp(): void
    {
        $this->dir = sys_get_temp_dir() . '/collection-item-image-file-' . bin2hex(random_bytes(4));
        mkdir($this->dir . '/files', 0777, true);
        foreach (['cover.jpg', 'logo.svg', 'shell.php'] as $name) {
            file_put_contents($this->dir . '/files/' . $name, 'bytes');
        }
        file_put_contents($this->dir . '/.env.local', 'SECRET=1');
    }

    // The folder removed whatever the test found
    protected function tearDown(): void
    {
        foreach (['files/cover.jpg', 'files/logo.svg', 'files/shell.php', '.env.local'] as $name) {
            unlink($this->dir . '/' . $name);
        }
        rmdir($this->dir . '/files');
        rmdir($this->dir);
    }

    // An image of the folder is handed over, its source kept
    public function testAnImageOfTheFolderIsHandedOver(): void
    {
        $file = CollectionItemImageFile::resolve($this->dir . '/files', 'cover.jpg');

        $this->assertNotNull($file);
        $this->assertSame(realpath($this->dir . '/files/cover.jpg'), $file->getPathname());
        $this->assertFalse($file->isRemoveReplacedFile());
        $this->assertFalse($file->isRemoveReplacedFileOnError());
    }

    /** @return array<string, array{mixed}> */
    public static function refusedProvider(): array
    {
        return [
            'leaving the folder' => ['../.env.local'],
            'absolute' => ['/etc/passwd'],
            'svg' => ['logo.svg'],
            'script' => ['shell.php'],
            'missing' => ['missing.jpg'],
            'not a string' => [['cover.jpg']],
        ];
    }

    // Anything else is ignored
    #[DataProvider('refusedProvider')]
    public function testAnythingElseIsIgnored(mixed $relative): void
    {
        $this->assertNull(CollectionItemImageFile::resolve($this->dir . '/files', $relative));
    }
}
