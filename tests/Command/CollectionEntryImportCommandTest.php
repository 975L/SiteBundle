<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Command;

use c975L\SiteBundle\Command\CollectionEntryImportCommand;
use c975L\SiteBundle\Entity\CollectionEntry;
use c975L\SiteBundle\Repository\CollectionEntryRepository;
use Doctrine\ORM\EntityManagerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\String\Slugger\AsciiSlugger;

class CollectionEntryImportCommandTest extends TestCase
{
    private string $projectDir;

    // Sandboxes each test behind its own throwaway project directory, so real filesystem reads
    // (json-file/images-dir) can be exercised safely - same pattern as ThemeVariablesCssListenerTest
    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/collection-entry-import-test-' . uniqid();
        mkdir($this->projectDir . '/images', 0777, true);
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    private function removeDirectory(string $dir): void
    {
        if (!is_dir($dir)) {
            return;
        }

        foreach (scandir($dir) as $entry) {
            if ('.' === $entry || '..' === $entry) {
                continue;
            }

            $path = $dir . '/' . $entry;
            is_dir($path) ? $this->removeDirectory($path) : unlink($path);
        }

        rmdir($dir);
    }

    private function writeJson(array $rows): void
    {
        file_put_contents($this->projectDir . '/entries.json', json_encode($rows));
    }

    private function createTester(
        EntityManagerInterface $em,
        CollectionEntryRepository $repository
    ): CommandTester {
        return new CommandTester(new CollectionEntryImportCommand($em, $repository, new AsciiSlugger(), $this->projectDir));
    }

    public function testExecuteFailsWhenGroupOptionIsMissing(): void
    {
        $tester = $this->createTester(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(CollectionEntryRepository::class)
        );

        $statusCode = $tester->execute(['--json-file' => 'entries.json']);

        $this->assertSame(Command::FAILURE, $statusCode);
        $this->assertStringContainsString('--group', $tester->getDisplay());
    }

    public function testExecuteFailsWhenJsonFileDoesNotExist(): void
    {
        $tester = $this->createTester(
            $this->createStub(EntityManagerInterface::class),
            $this->createStub(CollectionEntryRepository::class)
        );

        $statusCode = $tester->execute(['--group' => 'projects', '--json-file' => 'missing.json']);

        $this->assertSame(Command::FAILURE, $statusCode);
        $this->assertStringContainsString('File not found', $tester->getDisplay());
    }

    public function testExecuteCreatesAndFlushesNewEntries(): void
    {
        $this->writeJson([
            ['title' => 'Papa Câlin', 'description' => 'Des histoires', 'url' => 'https://papa-calin.com'],
        ]);

        $repository = $this->createStub(CollectionEntryRepository::class);
        $repository->method('findByGroup')->willReturn([]);
        $repository->method('countByGroup')->willReturn(0);

        $persisted = null;
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->once())->method('persist')
            ->with($this->isInstanceOf(CollectionEntry::class))
            ->willReturnCallback(function (CollectionEntry $entry) use (&$persisted): void {
                $persisted = $entry;
            });
        $em->expects($this->once())->method('flush');

        $tester = $this->createTester($em, $repository);
        $statusCode = $tester->execute(['--group' => 'projects', '--json-file' => 'entries.json']);

        $this->assertSame(Command::SUCCESS, $statusCode);
        $this->assertStringContainsString('1 entries created and flushed. 0 skipped.', $tester->getDisplay());
        $this->assertSame('papa-calin', $persisted->getSlug());
    }

    // Two different titles that normalize to the same slug within one run must still end up unique -
    // neither is flushed yet, so a DB-only collision check (findByGroup, snapshotted before the loop)
    // alone wouldn't catch this
    public function testExecuteDeduplicatesSlugsWithinTheSameRun(): void
    {
        $this->writeJson([
            ['title' => 'Papa Câlin'],
            ['title' => 'Papa Calin'],
        ]);

        $repository = $this->createStub(CollectionEntryRepository::class);
        $repository->method('findByGroup')->willReturn([]);
        $repository->method('countByGroup')->willReturn(0);

        $persistedSlugs = [];
        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->exactly(2))->method('persist')
            ->willReturnCallback(function (CollectionEntry $entry) use (&$persistedSlugs): void {
                $persistedSlugs[] = $entry->getSlug();
            });

        $tester = $this->createTester($em, $repository);
        $tester->execute(['--group' => 'projects', '--json-file' => 'entries.json']);

        $this->assertSame(['papa-calin', 'papa-calin-2'], $persistedSlugs);
    }

    // A title already present in the group (per findByGroup) is skipped, so re-running the command
    // on the same JSON is idempotent
    public function testExecuteSkipsAlreadyImportedTitles(): void
    {
        $this->writeJson([['title' => 'Papa Câlin']]);

        $existing = (new CollectionEntry())->setGroup('projects')->setTitle('Papa Câlin');

        $repository = $this->createStub(CollectionEntryRepository::class);
        $repository->method('findByGroup')->willReturn([$existing]);
        $repository->method('countByGroup')->willReturn(1);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $tester = $this->createTester($em, $repository);
        $statusCode = $tester->execute(['--group' => 'projects', '--json-file' => 'entries.json']);

        $this->assertSame(Command::SUCCESS, $statusCode);
        $this->assertStringContainsString('0 entries created and flushed. 1 skipped.', $tester->getDisplay());
    }

    public function testExecuteSkipsRowsWithNoTitle(): void
    {
        $this->writeJson([['description' => 'No title here']]);

        $repository = $this->createStub(CollectionEntryRepository::class);
        $repository->method('findByGroup')->willReturn([]);
        $repository->method('countByGroup')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');

        $tester = $this->createTester($em, $repository);
        $tester->execute(['--group' => 'projects', '--json-file' => 'entries.json']);

        $this->assertStringContainsString('0 entries created and flushed. 1 skipped.', $tester->getDisplay());
    }

    // --dry-run never touches persist()/flush(), only reports what would happen
    public function testDryRunDoesNotPersistOrFlush(): void
    {
        $this->writeJson([['title' => 'Papa Câlin']]);

        $repository = $this->createStub(CollectionEntryRepository::class);
        $repository->method('findByGroup')->willReturn([]);
        $repository->method('countByGroup')->willReturn(0);

        $em = $this->createMock(EntityManagerInterface::class);
        $em->expects($this->never())->method('persist');
        $em->expects($this->never())->method('flush');

        $tester = $this->createTester($em, $repository);
        $statusCode = $tester->execute(['--group' => 'projects', '--json-file' => 'entries.json', '--dry-run' => true]);

        $this->assertSame(Command::SUCCESS, $statusCode);
        $this->assertStringContainsString('1 entries would be created. 0 skipped.', $tester->getDisplay());
    }

    public function testExecuteWarnsWhenReferencedImageFileIsMissing(): void
    {
        $this->writeJson([['title' => 'Papa Câlin', 'image' => 'missing.webp']]);

        $repository = $this->createStub(CollectionEntryRepository::class);
        $repository->method('findByGroup')->willReturn([]);
        $repository->method('countByGroup')->willReturn(0);

        $tester = $this->createTester($this->createStub(EntityManagerInterface::class), $repository);
        $tester->execute([
            '--group' => 'projects',
            '--json-file' => 'entries.json',
            '--images-dir' => 'images',
            '--dry-run' => true,
        ]);

        $this->assertStringContainsString('Image not found', $tester->getDisplay());
    }
}
