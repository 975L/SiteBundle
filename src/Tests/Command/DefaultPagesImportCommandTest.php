<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Command;

use c975L\SiteBundle\Command\DefaultPagesImportCommand;
use c975L\SiteBundle\Service\DefaultPagesImporter;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

// Lives under src/Tests (not a sibling tests/ dir) so it stays autoloadable by consuming apps,
// whose attribute route loader recursively reflects every class under the bundle root
class DefaultPagesImportCommandTest extends TestCase
{
    private function createTester(array $importResult): CommandTester
    {
        $importer = $this->createStub(DefaultPagesImporter::class);
        $importer->method('import')->willReturn($importResult);

        return new CommandTester(new DefaultPagesImportCommand($importer));
    }

    // At least one page created: a success message reporting both counts is shown
    public function testExecuteReportsCreatedAndSkippedCounts(): void
    {
        $tester = $this->createTester(['created' => 2, 'skipped' => 1]);

        $statusCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $statusCode);
        $this->assertStringContainsString('2 page(s) created, 1 already existing skipped.', $tester->getDisplay());
    }

    // Nothing created (every default page already exists): a warning is shown instead, still a success
    public function testExecuteWarnsWhenNothingWasCreated(): void
    {
        $tester = $this->createTester(['created' => 0, 'skipped' => 5]);

        $statusCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $statusCode);
        $this->assertStringContainsString('All default pages already exist, nothing was created.', $tester->getDisplay());
    }
}
