<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\SiteBundle\Entity\Page;

// One row of the Health check dashboard: a page read in one language. Handed to ContentQualityAnalyzer as the entry's 'source' (ConfigBundle only ever passes it back, knowing nothing of it), so the "go to the block" link of every offence it finds opens the very screen the row's own edit link opens - the language the offence was read in, not the one the site is written in
readonly class PageLocaleSource
{
    public function __construct(
        public Page $page,
        public string $locale,
    ) {
    }
}
