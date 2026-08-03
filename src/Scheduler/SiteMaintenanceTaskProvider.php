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
            // Smoke test, nightly and late enough for the backups and the sitemaps to be done. It stays a deployment gate first (see SmokeTestCommand), but the nightly run costs nothing and answers what no health check does: "is a published page down right now". A non-zero exit code sends the RunCommandMessage to the failure transport, so a broken page surfaces as a back-office alert when a failure transport is configured, rather than as a line in a log nobody reads
            new MaintenanceTask('# #(5-7) * * *', 'c975l:site:smoke-test'),
        ];
    }
}
