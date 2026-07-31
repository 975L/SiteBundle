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
        $tester = $this->createTester(['created' => 2, 'skipped' => 1, 'summarised' => []]);

        $statusCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $statusCode);
        $this->assertStringContainsString('2 page(s) created, 1 already existing skipped.', $tester->getDisplay());
    }

    // Nothing created (every default page already exists): a warning is shown instead, still a success
    public function testExecuteWarnsWhenNothingWasCreated(): void
    {
        $tester = $this->createTester(['created' => 0, 'skipped' => 5, 'summarised' => []]);

        $statusCode = $tester->execute([]);

        $this->assertSame(Command::SUCCESS, $statusCode);
        $this->assertStringContainsString('All default pages already exist, nothing was created', $tester->getDisplay());
    }

    // Filling a description in is the only thing the command rewrites on a page that already existed, so it must name every one of them rather than report a count
    public function testExecuteNamesEveryPageWhoseDescriptionWasFilledIn(): void
    {
        $tester = $this->createTester(['created' => 0, 'skipped' => 9, 'summarised' => ['cookies', 'copyright']]);

        $tester->execute([]);

        $display = $tester->getDisplay();
        $this->assertStringContainsString('2 description(s) filled in on existing pages', $display);
        $this->assertStringContainsString('cookies', $display);
        $this->assertStringContainsString('copyright', $display);
    }

    // Nothing was rewritten: no section about descriptions at all, so a re-run stays quiet
    public function testExecuteSaysNothingAboutDescriptionsWhenNoneWereFilledIn(): void
    {
        $tester = $this->createTester(['created' => 0, 'skipped' => 9, 'summarised' => []]);

        $tester->execute([]);

        $this->assertStringNotContainsString('description(s) filled in', $tester->getDisplay());
    }
}
