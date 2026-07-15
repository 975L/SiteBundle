<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Command;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;
use Symfony\Component\Finder\Finder;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Email;
use Symfony\Component\Process\Process;
use DateTimeImmutable;
use DateTime;

/**
 * Console command to back up the database and user files in public/, replacing BackupServer.sh.
 *
 * Usage:
 *   php bin/console site:backup           # partial backup (files modified since last run)
 *   php bin/console site:backup --full    # complete backup (all files + archive tables)
 *   php bin/console site:backup --report  # also send a summary email after backup
 *
 * All settings are managed via ConfigBundle (site-backup-* keys).
 * MySQL credentials (host/user/password) are written to a temporary file at runtime
 * and deleted immediately after the backup completes.
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2026 975L <contact@975l.com>
 */
#[AsCommand(
    name: 'c975l:site:backup',
    description: 'Backs up the database and user files'
)]
class BackupCommand extends Command
{
    // Files/dirs in public/ excluded from every backup (framework assets, not user data)
    private const STANDARD_EXCLUDES = [
        'assets',
        'bundles',
        'humans.txt',
        'index.php',
        'prepend.inc.php',
        'robots.txt',
    ];

    private string $projectDir;
    private string $credentialsFile; // path to the runtime-generated temp file
    private string $database;
    private string $siteDomain;
    private string $mailto;
    private DateTimeImmutable $startedAt;
    private string $backupFolder;
    private string $finalFolder;
    private string $report = '';
    private array $errors = [];

    public function __construct(
        private readonly ParameterBagInterface $parameterBag,
        private readonly ConfigServiceInterface $configService,
        private readonly MailerInterface $mailer,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addOption('full', null, InputOption::VALUE_NONE, 'Complete backup: all files + archive tables + whole DB dump')
            ->addOption('report', null, InputOption::VALUE_NONE, 'Send a summary email after the backup');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $isFull = $input->getOption('full');
        $sendReport = $input->getOption('report');

        $this->projectDir = $this->parameterBag->get('kernel.project_dir');
        $this->database = (string) $this->configService->get('site-backup-database');
        $this->siteDomain = parse_url((string) $this->configService->get('site-url'), PHP_URL_HOST) ?? '';
        $this->mailto = (string) $this->configService->get('site-backup-mailto');

        if (empty($this->database)) {
            $io->error('site-backup-database is not configured in ConfigBundle.');
            return Command::FAILURE;
        }

        $this->startedAt = new \DateTimeImmutable();
        $this->backupFolder = $this->projectDir . '/var/backup';
        $this->finalFolder = sprintf('%s/%s/%s/%s',
            $this->backupFolder,
            $this->startedAt->format('Y'),
            $this->startedAt->format('Y-m'),
            $this->startedAt->format('Y-m-d')
        );
        if (!is_dir($this->finalFolder)) {
            mkdir($this->finalFolder, 0755, true);
        }

        $this->credentialsFile = $this->createTempCredentialsFile();
        try {
            $this->backupMySql($isFull);
            $this->backupFolders($isFull);
            $this->cleanup();
        } finally {
            unlink($this->credentialsFile);
        }

        if (!empty($this->errors)) {
            $this->sendErrorReport();
            $io->error('Backup completed with errors.');
            return Command::FAILURE;
        }

        if ($sendReport) {
            $this->sendReport();
        }

        $io->success('Backup completed.');
        return Command::SUCCESS;
    }

    private function backupMySql(bool $isFull): void
    {
        $db = $this->database;
        $dateTime = $this->startedAt->format('Y-m-d_-_H-i');

        $this->report .= sprintf("\nMySQL backup for \"%s\": %s\n", $db, $this->startedAt->format('Y-m-d H:i:s'));

        $tables = $this->getMySqlTableList(
            "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '{$db}' AND TABLE_NAME NOT LIKE '%_archives' AND TABLE_TYPE != 'VIEW'"
        );
        foreach ($tables as $table) {
            $this->report .= "- {$table}\n";
            $this->dumpTable($db, $table, $this->finalFolder . "/{$db}_-_{$table}.sql");
        }
        $this->compressSqlFiles("MYSQL_-_{$db}_-_{$dateTime}_-_Tables.sql.tar.bz2");

        if ($isFull) {
            $this->report .= "\n> Tables *_archives in \"{$db}\"\n";
            $archiveTables = $this->getMySqlTableList(
                "SELECT TABLE_NAME FROM INFORMATION_SCHEMA.TABLES WHERE TABLE_SCHEMA = '{$db}' AND TABLE_NAME LIKE '%_archives'"
            );
            foreach ($archiveTables as $table) {
                $this->report .= "- {$table}\n";
                $this->dumpTable($db, $table, $this->finalFolder . "/{$db}_-_{$table}.sql");
            }

            $this->report .= "\n> WHOLE database {$db}\n";
            $this->dumpDatabase($db, $this->finalFolder . "/{$db}_-_WHOLE_DATABASE.sql");
            $this->compressSqlFiles("MYSQL_-_{$db}_-_{$dateTime}_-_Archives.sql.tar.bz2");
        }
    }

    private function createTempCredentialsFile(): string
    {
        $host = (string) ($this->configService->get('site-backup-db-host') ?: 'localhost');
        $user = (string) $this->configService->get('site-backup-db-user');
        $password = (string) $this->configService->get('site-backup-db-password');

        $tmpFile = tempnam(sys_get_temp_dir(), 'site_backup_');
        chmod($tmpFile, 0600);
        file_put_contents($tmpFile, "[client]\nhost={$host}\nuser={$user}\npassword={$password}\n");

        return $tmpFile;
    }

    private function getMySqlTableList(string $query): array
    {
        $process = new Process([
            'mysql',
            '--defaults-extra-file=' . $this->credentialsFile,
            '--database=' . $this->database,
            '--silent', '--raw',
            '--execute=' . $query,
        ]);
        $process->setTimeout(60);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->errors[] = 'MySQL table list failed: ' . $process->getErrorOutput();
            return [];
        }

        return array_values(array_filter(
            array_map('trim', explode("\n", $process->getOutput())),
            fn($t) => $t && $t !== 'TABLE_NAME'
        ));
    }

    private function dumpTable(string $db, string $table, string $outFile): void
    {
        $process = new Process([
            'mysqldump',
            '--defaults-extra-file=' . $this->credentialsFile,
            '--skip-comments', '--compact', '--force', '--lock-tables',
            '--quick', '--single-transaction', '--triggers', '--hex-blob',
            $db, $table,
        ]);
        $process->setTimeout(300);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->errors[] = "mysqldump failed for table {$table}: " . $process->getErrorOutput();
            return;
        }

        file_put_contents($outFile, $process->getOutput());
    }

    private function dumpDatabase(string $db, string $outFile): void
    {
        $process = new Process([
            'mysqldump',
            '--defaults-extra-file=' . $this->credentialsFile,
            '--skip-comments', '--compact', '--force', '--lock-tables',
            '--quick', '--single-transaction', '--triggers', '--hex-blob',
            $db,
        ]);
        $process->setTimeout(3600);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->errors[] = "mysqldump failed for whole database {$db}: " . $process->getErrorOutput();
            return;
        }

        file_put_contents($outFile, $process->getOutput());
    }

    private function compressSqlFiles(string $archiveName): void
    {
        $sqlFiles = glob($this->finalFolder . '/*.sql');
        if (empty($sqlFiles)) {
            return;
        }

        $process = new Process(array_merge(
            ['nice', 'tar', '--remove-files', '--bzip2', '--create', '--file', $archiveName],
            array_map('basename', $sqlFiles)
        ), $this->finalFolder);
        $process->setTimeout(600);
        $process->run();

        if (!$process->isSuccessful()) {
            $this->errors[] = 'SQL tar compression failed: ' . $process->getErrorOutput();
        }
    }

    private function backupFolders(bool $isFull): void
    {
        $dateTime = $this->startedAt->format('Y-m-d_-_H-i');
        $publicFolder = $this->projectDir . '/public';
        $excludeFile = $this->projectDir . '/config/backup_exclude.cnf';
        $dateTimeFile = $this->projectDir . '/var/BackupDateTimeFile';

        $this->report .= sprintf("\nFolders backup for \"%s\": %s\n", $this->siteDomain, $this->startedAt->format('Y-m-d H:i:s'));

        $doFull = $isFull || !file_exists($dateTimeFile);

        if ($doFull) {
            $this->report .= "COMPLETE Folders backup\n";
            // -C changes into $publicFolder so archive paths are relative (./medias/..., etc.)
            $args = ['nice', 'tar', '--bzip2', '--create', '-C', $publicFolder];
            foreach (self::STANDARD_EXCLUDES as $pattern) {
                $args[] = '--exclude=' . $pattern;
            }
            $args[] = '--exclude=sitemap-*';
            if (file_exists($excludeFile)) {
                $args[] = '--exclude-from=' . $excludeFile;
            }
            $args[] = '--file';
            $args[] = $this->finalFolder . "/WEBSITE_-_{$this->siteDomain}_-_{$dateTime}_-_Complete.tar.bz2";
            $args[] = '.';
            $process = new Process($args);
            $process->setTimeout(3600);
            $process->run();

            if (!$process->isSuccessful()) {
                $this->errors[] = 'Complete folders tar failed: ' . $process->getErrorOutput();
            }
        } else {
            $lastBackupTime = filemtime($dateTimeFile);
            $excludedTopLevel = array_flip(self::STANDARD_EXCLUDES);

            $finder = (new Finder())
                ->files()
                ->in($publicFolder)
                ->filter(function (\SplFileInfo $f) use ($publicFolder, $excludedTopLevel) {
                    $relative = substr($f->getRealPath(), strlen($publicFolder) + 1);
                    $topLevel = strtok($relative, '/');
                    if (isset($excludedTopLevel[$topLevel])) {
                        return false;
                    }
                    return !str_starts_with($topLevel, 'sitemap-');
                })
                ->filter(fn(\SplFileInfo $f) => $f->getMTime() > $lastBackupTime);

            $modifiedFiles = array_map(
                fn(\SplFileInfo $f) => $f->getRealPath(),
                iterator_to_array($finder, false)
            );

            if (!empty($modifiedFiles)) {
                $this->report .= "PARTIAL Folders backup\n";
                foreach ($modifiedFiles as $file) {
                    $this->report .= $file . "\n";
                }
                $process = new Process(array_merge(
                    ['nice', 'tar', '--bzip2', '--create',
                        '--file', $this->finalFolder . "/WEBSITE_-_{$this->siteDomain}_-_{$dateTime}_-_Partial.tar.bz2",
                    ],
                    $modifiedFiles
                ));
                $process->setTimeout(3600);
                $process->run();

                if (!$process->isSuccessful()) {
                    $this->errors[] = 'Partial folders tar failed: ' . $process->getErrorOutput();
                }
            } else {
                $this->report .= "NO FILE to save\n";
            }
        }

        // Record start time so the next partial backup only captures newer files
        touch($dateTimeFile, $this->startedAt->getTimestamp());
    }

    private function cleanup(): void
    {
        foreach ((new Finder())->files()->in($this->finalFolder)->size('< 50') as $file) {
            unlink($file->getRealPath());
        }

        $this->deleteEmptyDirectories($this->backupFolder);

        $duration = time() - $this->startedAt->getTimestamp();
        $this->report .= sprintf(
            "\nEnd of backup: %s - Duration: %d minutes and %d seconds\n",
            (new \DateTime())->format('Y-m-d H:i:s'),
            intdiv($duration, 60),
            $duration % 60
        );

        if (!empty($this->errors)) {
            $this->report .= "\nERRORS:\n" . implode("\n", $this->errors) . "\n";
        }
    }

    private function deleteEmptyDirectories(string $path): void
    {
        foreach (glob($path . '/*', GLOB_ONLYDIR) as $dir) {
            $this->deleteEmptyDirectories($dir);
            if (!(new \FilesystemIterator($dir))->valid()) {
                rmdir($dir);
            }
        }
    }

    private function sendErrorReport(): void
    {
        if (empty($this->mailto) || empty($this->siteDomain)) {
            return;
        }

        $email = (new Email())
            ->from((string) $this->configService->get('email-from'))
            ->to($this->mailto)
            ->subject('[ERROR] Backup failed - ' . $this->siteDomain)
            ->text(implode("\n", $this->errors) . "\n\nFull report:\n" . $this->report);

        $this->mailer->send($email);
    }

    private function sendReport(): void
    {
        if (empty($this->mailto) || empty($this->siteDomain)) {
            return;
        }

        $email = (new Email())
            ->from((string) $this->configService->get('email-from'))
            ->to($this->mailto)
            ->subject('Backup Report - ' . $this->siteDomain)
            ->text($this->report);

        $this->mailer->send($email);
    }
}
