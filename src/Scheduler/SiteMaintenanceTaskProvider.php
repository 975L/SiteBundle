<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Scheduler;

use c975L\ConfigBundle\Scheduler\MaintenanceTask;
use c975L\ConfigBundle\Scheduler\MaintenanceTaskProviderInterface;

// The commands this bundle needs run on a cadence, declared here rather than listed by every site in its own MaintenanceSchedule
class SiteMaintenanceTaskProvider implements MaintenanceTaskProviderInterface
{
    public function getMaintenanceTasks(): array
    {
        return [
            // Failed messenger rows past their retention, nightly, once the backups have had their window
            new MaintenanceTask('# #(2-4) * * *', 'c975l:site:messenger-cleanup'),
        ];
    }
}
