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
    ) {}

    public function getSchedule(): Schedule
    {
        return (new Schedule())
            ->stateful($this->cache)
            ->add(RecurringMessage::cron('5 0 * * *', new RunCommandMessage('app:sitemaps:create')))
            ->add(RecurringMessage::cron('7 */6 * * *', new RunCommandMessage('c975l:site:backup')))
            ->add(RecurringMessage::cron('0 3 * * *', new RunCommandMessage('c975l:site:messenger-cleanup')))
            ->add(RecurringMessage::cron('7 3 * * 1',   new RunCommandMessage('c975l:site:backup --report')))
            // Weekly: every registered health check provider is free (no paid API involved)
            ->add(RecurringMessage::cron('0 4 * * 0',   new RunCommandMessage('c975l:health-check:run --kind=pagespeed --kind=security-headers --kind=w3c-html --kind=w3c-css --kind=content-quality --kind=ssl-certificate --kind=mixed-content --kind=seo-files --kind=redirect-chains')))
        ;
    }
}
