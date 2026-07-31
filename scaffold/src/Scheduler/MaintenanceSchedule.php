<?php

namespace App\Scheduler;

use Symfony\Component\Console\Messenger\RunCommandMessage;
use Symfony\Component\Scheduler\Attribute\AsSchedule;
use Symfony\Component\Scheduler\RecurringMessage;
use Symfony\Component\Scheduler\Schedule;
use Symfony\Component\Scheduler\ScheduleProviderInterface;
use Symfony\Contracts\Cache\CacheInterface;

#[AsSchedule('site')]
class MaintenanceSchedule implements ScheduleProviderInterface
{
    public function __construct(
        private readonly CacheInterface $cache,
    ) {
    }

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->stateful($this->cache)
            ->add(RecurringMessage::cron('5 0 * * *', new RunCommandMessage('c975l:sitemaps:create')))
            ->add(RecurringMessage::cron('7 */6 * * *', new RunCommandMessage('c975l:config:backup')))
            ->add(RecurringMessage::cron('0 3 * * *', new RunCommandMessage('c975l:site:messenger-cleanup')))
            // Weekly digest of the backups above, on its own entry rather than as --report on the Monday run: a summary riding on a backup only exists if that run reaches its last line, and no mail at all is what nobody notices
            ->add(RecurringMessage::cron('7 3 * * 1', new RunCommandMessage('c975l:config:backup:digest')))
            // These two ask for a cadence, never for kinds: every provider states its own (see ConfigBundle's AsHealthCheck, weekly unless it says otherwise), so installing or removing a bundle is already accounted for here and these lines never need editing
            ->add(RecurringMessage::cron('0 4 * * 0', new RunCommandMessage('c975l:health-check:run --frequency=weekly')))
            // The heavy ones, alone on their own entry: a gallery declares one url per photo, by far the longest run
            ->add(RecurringMessage::cron('0 6 1 * *', new RunCommandMessage('c975l:health-check:run --frequency=monthly')))
        ;
    }
}
