<?php
/*
 * (c) 2019: 975L <contact@975l.com>
 * (c) 2019: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Twig\TemplateExists;
use PHPUnit\Framework\TestCase;

class TemplateExistsTest extends TestCase
{
    private string $projectDir;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/c975l-template-exists-test-' . uniqid();
        mkdir($this->projectDir . '/templates', 0775, true);
        file_put_contents($this->projectDir . '/templates/home.html.twig', 'x');
    }

    protected function tearDown(): void
    {
        unlink($this->projectDir . '/templates/home.html.twig');
        rmdir($this->projectDir . '/templates');
        rmdir($this->projectDir);
    }

    private function createExtension(): TemplateExists
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('getContainerParameter')->willReturn($this->projectDir);

        return new TemplateExists($configService);
    }

    // A template present under templates/ is found
    public function testTemplateExistsReturnsTrueWhenTemplateIsPresent(): void
    {
        $this->assertTrue($this->createExtension()->templateExists('home.html.twig'));
    }

    // A template absent from templates/ is reported as missing
    public function testTemplateExistsReturnsFalseWhenTemplateIsMissing(): void
    {
        $this->assertFalse($this->createExtension()->templateExists('missing.html.twig'));
    }
}
