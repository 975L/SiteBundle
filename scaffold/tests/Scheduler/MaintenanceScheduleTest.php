<?php

namespace App\Tests\Scheduler;

use App\Scheduler\MaintenanceSchedule;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Generator\MessageContext;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Contracts\Cache\CacheInterface;

class MaintenanceScheduleTest extends TestCase
{
    #[DataProvider('recurringMessages')]
    public function testGetSchedule(int $index, string $cronExpression, string $command): void
    {
        $cache = $this->createStub(CacheInterface::class);
        $schedule = (new MaintenanceSchedule($cache))->getSchedule();

        // Checks the schedule is stateful with the provided cache
        $this->assertInstanceOf(Schedule::class, $schedule);
        $this->assertSame($cache, $schedule->getState());

        $recurringMessages = $schedule->getRecurringMessages();
        $this->assertCount(5, $recurringMessages);

        // Checks the cron expression and the command carried by the message
        $recurringMessage = $recurringMessages[$index];
        $this->assertSame($cronExpression, (string) $recurringMessage->getTrigger());

        $context = new MessageContext('site', $recurringMessage->getId(), $recurringMessage->getTrigger(), new \DateTimeImmutable());
        $messages = iterator_to_array($recurringMessage->getMessages($context));
        $this->assertInstanceOf(RunCommandMessage::class, $messages[0]);
        $this->assertSame($command, $messages[0]->input);
    }

// Provides the expected recurring messages, in the order they are added
    public static function recurringMessages(): array
    {
        return [
            [0, '5 0 * * *', 'app:sitemaps:create'],
            [1, '7 */6 * * *', 'c975l:site:backup'],
            [2, '0 3 * * *', 'c975l:site:messenger-cleanup'],
            [3, '7 3 * * 1', 'c975l:site:backup --report'],
            [4, '0 4 * * 0', 'c975l:health-check:run --kind=pagespeed --kind=security-headers --kind=w3c-html --kind=w3c-css --kind=content-quality --kind=ssl-certificate --kind=mixed-content --kind=seo-files --kind=redirect-chains'],
        ];
    }
}
