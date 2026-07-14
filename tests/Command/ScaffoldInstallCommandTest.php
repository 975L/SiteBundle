<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Command;

use c975L\SiteBundle\Command\ScaffoldInstallCommand;
use c975L\SiteBundle\Service\ScaffoldInstaller;
use PHPUnit\Framework\Attributes\AllowMockObjectsWithoutExpectations;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

class ScaffoldInstallCommandTest extends TestCase
{
    // ScaffoldInstaller is only ever called once per execute(), no further expectations needed here
    #[AllowMockObjectsWithoutExpectations]
    public function testExecuteReportsCopiedBackedUpAndSkippedCounts(): void
    {
        $scaffoldInstaller = $this->createStub(ScaffoldInstaller::class);
        $scaffoldInstaller->method('install')->willReturn(['copied' => 3, 'backedUp' => 1, 'skipped' => 5]);
        $tester = new CommandTester(new ScaffoldInstallCommand($scaffoldInstaller));

        $statusCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $statusCode);
        $this->assertStringContainsString('3 fichier(s) copié(s), 1 sauvegardé(s) dans existingFiles/, 5 déjà à jour.', $tester->getDisplay());
    }
}
