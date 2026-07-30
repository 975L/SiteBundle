<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\SiteBundle\Repository\FontRepository;
use c975L\UiBundle\Contract\FontProviderInterface;

// Offers the uploaded fonts' family names to the theme-font-family-* configs, auto-discovered by FontProviderPass
class FontService implements FontProviderInterface
{
    public function __construct(
        private readonly FontRepository $fontRepository,
    ) {
    }

    public function getFonts(): array
    {
        // Already DISTINCT at the query level, but unordered - the select needs a stable, alphabetical list
        $fonts = $this->fontRepository->findDistinctNames();
        sort($fonts);

        return $fonts;
    }
}
