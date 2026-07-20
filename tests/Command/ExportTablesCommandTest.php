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
use c975L\SiteBundle\Command\ExportTablesCommand;
use PHPUnit\Framework\TestCase;
use Symfony\Component\DependencyInjection\ParameterBag\ParameterBagInterface;

class ExportTablesCommandTest extends TestCase
{
    private function createParameterBag(): ParameterBagInterface
    {
        $bag = $this->createStub(ParameterBagInterface::class);
        $bag->method('get')->willReturnCallback(
            fn (string $name): string => 'kernel.project_dir' === $name ? sys_get_temp_dir() : ''
        );

        return $bag;
    }

    // A bogus host/user makes the mysql client fail instantly with "Access denied", driving
    // getTableList() into its error branch without ever needing a real database
    private function createConfigService(): ConfigServiceInterface
    {
        $values = [
            'site-backup-database' => 'test_db',
            'site-backup-db-host' => '127.0.0.1',
            'site-backup-db-user' => 'c975l_test_invalid_user',
            'site-backup-db-password' => 'wrong',
        ];
        $service = $this->createStub(ConfigServiceInterface::class);
        $service->method('get')->willReturnCallback(fn (string $key) => $values[$key] ?? '');

        return $service;
    }

    public function testConfigureHasPrefixAndOutputOptionsWithDefaultPrefix(): void
    {
        $command = new ExportTablesCommand($this->createParameterBag(), $this->createConfigService());

        $this->assertTrue($command->getDefinition()->hasOption('prefix'));
        $this->assertSame('site_', $command->getDefinition()->getOption('prefix')->getDefault());
        $this->assertTrue($command->getDefinition()->hasOption('output'));
    }

    public function testExportTablesReturnsErrorWhenDatabaseNotConfigured(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('');

        $command = new ExportTablesCommand($this->createParameterBag(), $configService);

        $result = $command->exportTables();

        $this->assertSame('site-backup-database is not configured in ConfigBundle.', $result['error']);
        $this->assertSame([], $result['tables']);
    }

    public function testExportTablesReturnsErrorWhenMysqlFailsToListTables(): void
    {
        $command = new ExportTablesCommand($this->createParameterBag(), $this->createConfigService());

        $result = $command->exportTables();

        $this->assertNotNull($result['error']);
        $this->assertStringContainsString('mysql failed while listing tables', $result['error']);
        $this->assertSame([], $result['tables']);
    }
}
