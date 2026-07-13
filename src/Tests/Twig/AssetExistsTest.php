<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Twig\AssetExists;
use PHPUnit\Framework\TestCase;

// Lives under src/Tests (not a sibling tests/ dir) so it stays autoloadable by consuming apps,
// whose attribute route loader recursively reflects every class under the bundle root
class AssetExistsTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/c975l-asset-exists-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0775, true);
        mkdir($this->projectDir . '/assets', 0775, true);
        file_put_contents($this->projectDir . '/public/logo.png', 'x');
        file_put_contents($this->projectDir . '/assets/app.js', 'x');
    }

    protected function tearDown(): void
    {
        unlink($this->projectDir . '/public/logo.png');
        unlink($this->projectDir . '/assets/app.js');
        rmdir($this->projectDir . '/public');
        rmdir($this->projectDir . '/assets');
        rmdir($this->projectDir);
    }

    private function createExtension(): AssetExists
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('getContainerParameter')->willReturn($this->projectDir);

        return new AssetExists($configService);
    }

    // A file present under public/ is found
    public function testAssetExistsFindsFileUnderPublic(): void
    {
        $this->assertTrue($this->createExtension()->assetExists('logo.png'));
    }

    // A file present under assets/ (AssetMapper source) is also found
    public function testAssetExistsFindsFileUnderAssets(): void
    {
        $this->assertTrue($this->createExtension()->assetExists('app.js'));
    }

    // A file present in neither directory is reported as missing
    public function testAssetExistsReturnsFalseForMissingFile(): void
    {
        $this->assertFalse($this->createExtension()->assetExists('missing.png'));
    }
}
