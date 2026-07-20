<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Command;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Command\BackupCommand;
use Doctrine\DBAL\Connection;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;

class BackupCommandTest extends TestCase
{
    private string $projectDir;
    private array $callOrder;

    protected function setUp(): void
    {
        $this->projectDir = sys_get_temp_dir() . '/c975l-backup-command-test-' . uniqid();
        mkdir($this->projectDir . '/public', 0775, true);
        $this->callOrder = [];
    }

    protected function tearDown(): void
    {
        $this->removeDirectory($this->projectDir);
    }

    // Recursively deletes a directory tree (no external dependency needed for this test-only cleanup)
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

    private function createParameterBag(): ParameterBagInterface
    {
        $bag = $this->createStub(ParameterBagInterface::class);
        $bag->method('get')->willReturnCallback(
            fn (string $name): string => 'kernel.project_dir' === $name ? $this->projectDir : ''
        );

        return $bag;
    }

    // A bogus host/user makes the mysql client fail instantly with "Access denied", driving execute()
    // into the sendErrorReport() path without ever needing a real backup to succeed
    private function createConfigService(): ConfigServiceInterface
    {
        $values = [
            'site-backup-database' => 'test_db',
            'site-backup-db-host' => '127.0.0.1',
            'site-backup-db-user' => 'c975l_test_invalid_user',
            'site-backup-db-password' => 'wrong',
            'site-url' => 'https://example.com',
            'site-backup-mailto' => 'admin@example.com',
            'email-from' => 'noreply@example.com',
        ];
        $service = $this->createStub(ConfigServiceInterface::class);
        $service->method('get')->willReturnCallback(fn (string $key) => $values[$key] ?? '');

        return $service;
    }

    // A long mysqldump/tar can leave the Doctrine connection idle long enough for MySQL to close it
    // (wait_timeout), so it must be explicitly closed before any further DB access - here, the mailer's
    // dispatch of the report email through the Doctrine Messenger transport
    public function testExecuteClosesConnectionBeforeSendingReport(): void
    {
        $connection = $this->createMock(Connection::class);
        $connection->expects($this->once())
            ->method('close')
            ->willReturnCallback(function (): void {
                $this->callOrder[] = 'close';
            });

        $mailer = $this->createMock(MailerInterface::class);
        $mailer->expects($this->once())
            ->method('send')
            ->willReturnCallback(function (): void {
                $this->callOrder[] = 'send';
            });

        $command = new BackupCommand(
            $this->createParameterBag(),
            $this->createConfigService(),
            $mailer,
            $connection,
        );

        $tester = new CommandTester($command);
        $tester->execute([]);

        $this->assertSame(['close', 'send'], $this->callOrder);
        $this->assertSame(Command::FAILURE, $tester->getStatusCode());
    }

    // Regression guard: MySQL is always dumped table by table now, there's no more --full/whole-database mode
    public function testConfigureHasNoFullOption(): void
    {
        $command = new BackupCommand(
            $this->createParameterBag(),
            $this->createConfigService(),
            $this->createStub(MailerInterface::class),
            $this->createStub(Connection::class),
        );

        $this->assertFalse($command->getDefinition()->hasOption('full'));
    }

    // The very first run has no BackupFullDateTimeFile marker yet, so the file backup goes complete
    public function testBackupFoldersGoesCompleteOnFirstRun(): void
    {
        $this->assertStringContainsString('COMPLETE Folders backup', $this->runAndCaptureReport());
    }

    // Once site-backup-full-interval-months calendar months have passed since the last complete run, the next run goes complete again instead of staying partial
    public function testBackupFoldersGoesCompleteAgainAfterFullIntervalElapsed(): void
    {
        mkdir($this->projectDir . '/var', 0775, true);
        touch($this->projectDir . '/var/BackupDateTimeFile', time() - 3600);
        // 2 months back guarantees at least 1 whole calendar month elapsed regardless of today's day-of-month
        touch($this->projectDir . '/var/BackupFullDateTimeFile', strtotime('-2 months'));

        $this->assertStringContainsString('COMPLETE Folders backup', $this->runAndCaptureReport());
    }

    // Within site-backup-full-interval-months of the last complete run, the file backup stays partial
    public function testBackupFoldersStaysPartialWithinFullInterval(): void
    {
        mkdir($this->projectDir . '/var', 0775, true);
        touch($this->projectDir . '/var/BackupDateTimeFile', time() - 3600);
        touch($this->projectDir . '/var/BackupFullDateTimeFile', time() - 3600);

        $this->assertStringNotContainsString('COMPLETE Folders backup', $this->runAndCaptureReport());
    }

    // Runs the command (always failing fast on bad DB creds, see createConfigService()) and returns the
    // error-report email's text body, which embeds the full run report including the folders-backup section
    private function runAndCaptureReport(): string
    {
        $capturedEmail = null;
        $mailer = $this->createStub(MailerInterface::class);
        $mailer->method('send')->willReturnCallback(function (Email $email) use (&$capturedEmail): void {
            $capturedEmail = $email;
        });

        $command = new BackupCommand(
            $this->createParameterBag(),
            $this->createConfigService(),
            $mailer,
            $this->createStub(Connection::class),
        );

        (new CommandTester($command))->execute([]);

        return $capturedEmail->getTextBody();
    }
}
