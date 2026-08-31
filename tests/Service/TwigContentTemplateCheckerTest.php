<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Service\TwigContentTemplateChecker;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Psr\Log\NullLogger;

class TwigContentTemplateCheckerTest extends TestCase
{
    private string $defaultPath;

    protected function setUp(): void
    {
        $this->defaultPath = sys_get_temp_dir() . '/site-twig-content-' . uniqid();

        mkdir($this->defaultPath . '/blocks', 0o777, true);
        file_put_contents($this->defaultPath . '/blocks/insert.html.twig', '<p>ok</p>');
    }

    protected function tearDown(): void
    {
        @unlink($this->defaultPath . '/blocks/insert.html.twig');
        @rmdir($this->defaultPath . '/blocks');
        @rmdir($this->defaultPath);
    }

    public function testAllowsATemplateOfTheSite(): void
    {
        $this->assertTrue($this->checker()->isAllowed('blocks/insert.html.twig'));
    }

    // The path BlockFixtureProvider writes: the bundle would otherwise break its own example
    public function testAllowsTheBundlesOwnExample(): void
    {
        $this->assertTrue($this->checker()->isAllowed('@c975LSite/examples/twig_content_example.html.twig'));
    }

    public function testRefusesATemplateThatDoesNotExist(): void
    {
        $this->assertFalse($this->checker()->isAllowed('blocks/nowhere.html.twig'));
    }

    // What the fix is for: any installed bundle's templates were within reach of a path typed in the back office
    public function testRefusesAnotherBundlesTemplate(): void
    {
        $this->assertFalse($this->checker()->isAllowed('@c975LUi/blocks/Alert.html.twig'));
        $this->assertFalse($this->checker()->isAllowed('@c975LSite/blocks/TwigContent.html.twig'));
    }

    public function testRefusesAPathClimbingOut(): void
    {
        $this->assertFalse($this->checker()->isAllowed('../config/packages/security.yaml'));
        $this->assertFalse($this->checker()->isAllowed('@c975LSite/examples/../blocks/TwigContent.html.twig'));
    }

    public function testRefusesANullByte(): void
    {
        $this->assertFalse($this->checker()->isAllowed("blocks/insert.html.twig\0.png"));
    }

    public function testRefusesAnEmptyPath(): void
    {
        $this->assertFalse($this->checker()->isAllowed(null));
        $this->assertFalse($this->checker()->isAllowed('   '));
    }

    // A refused block renders nothing at all, so the log line is the only trace left of what the page was pointing at
    public function testLogsARefusedPath(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger
            ->expects($this->once())
            ->method('warning')
            ->with('twig_content block refused a template path', ['path' => '@c975LUi/blocks/Alert.html.twig'])
        ;

        $this->assertFalse(new TwigContentTemplateChecker($this->defaultPath, $logger)->isAllowed('@c975LUi/blocks/Alert.html.twig'));
    }

    // An empty path is a block that never carried one: logging it would fill var/log/ with a non-event
    public function testDoesNotLogAnEmptyPath(): void
    {
        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects($this->never())->method('warning');

        $this->assertFalse(new TwigContentTemplateChecker($this->defaultPath, $logger)->isAllowed(null));
    }

    private function checker(): TwigContentTemplateChecker
    {
        return new TwigContentTemplateChecker($this->defaultPath, new NullLogger());
    }
}
