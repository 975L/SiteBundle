<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\ProcedureJsonReader;
use c975L\ConfigBundle\Management\ProcedureProviderInterface;

class ProcedureProvider implements ProcedureProviderInterface
{
    public function getProcedures(): array
    {
        return ProcedureJsonReader::read(\dirname(__DIR__, 2) . '/config/procedures.json');
    }
}
