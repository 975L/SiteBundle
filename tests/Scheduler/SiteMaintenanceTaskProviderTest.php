<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Scheduler;

use c975L\ConfigBundle\Scheduler\MaintenanceTask;
use c975L\ConfigBundle\Scheduler\ScheduleSpreader;
use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Command\MessengerCleanupCommand;
use c975L\SiteBundle\Scheduler\SiteMaintenanceTaskProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\RecurringMessage;

// A wrong command name or a malformed expression here only surfaces at worker start-up on a real site, where Schedule::add() throwing takes every other task down with it - hence checking both against the real classes rather than against a copy of the strings
class SiteMaintenanceTaskProviderTest extends TestCase
{
    private function commandName(string $class): string
    {
        $attributes = (new \ReflectionClass($class))->getAttributes(AsCommand::class);
        $this->assertNotEmpty($attributes, sprintf('%s carries no AsCommand attribute.', $class));

        return $attributes[0]->newInstance()->name;
    }

    public function testDeclaresOnlyMaintenanceTasks(): void
    {
        $tasks = (new SiteMaintenanceTaskProvider())->getMaintenanceTasks();

        $this->assertNotEmpty($tasks);
        $this->assertContainsOnlyInstancesOf(MaintenanceTask::class, $tasks);
    }

    public function testDeclaresTheMessengerCleanupCommandUnderItsRealName(): void
    {
        $commands = array_map(
            static fn (MaintenanceTask $task): string => strtok($task->command, ' '),
            (new SiteMaintenanceTaskProvider())->getMaintenanceTasks()
        );

        $this->assertContains($this->commandName(MessengerCleanupCommand::class), $commands);
    }

    public function testDeclaresEachCommandOnlyOnce(): void
    {
        $commands = array_map(
            static fn (MaintenanceTask $task): string => $task->command,
            (new SiteMaintenanceTaskProvider())->getMaintenanceTasks()
        );

        $this->assertSame($commands, array_unique($commands));
    }

    public function testEveryExpressionIsAcceptedByTheSpreader(): void
    {
        $configService = $this->createStub(ConfigServiceInterface::class);
        $configService->method('get')->willReturn('https://example.com');
        $scheduleSpreader = new ScheduleSpreader($configService, '/project');

        foreach ((new SiteMaintenanceTaskProvider())->getMaintenanceTasks() as $task) {
            $this->assertInstanceOf(
                RecurringMessage::class,
                $scheduleSpreader->spread($task->expression, new RunCommandMessage($task->command))
            );
        }
    }
}
