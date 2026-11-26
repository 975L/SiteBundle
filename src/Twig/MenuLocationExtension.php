<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Twig;

use c975L\SiteBundle\Service\MenuLocations;
use Twig\Attribute\AsTwigFunction;

// The menu a layout draws at a location: the variant's own once it holds something, the site's otherwise, so a variant left empty never leaves a page without its navbar or footer
class MenuLocationExtension
{
    public function __construct(
        private readonly MenuLocations $menuLocations,
        private readonly MenuExtension $menuExtension,
    ) {
    }

    // The location to read ("navbar.members", or "navbar" back), its blocks coming from the same cache menu_blocks() reads
    #[AsTwigFunction('menu_location')]
    public function location(string $base, ?string $variant = null): string
    {
        $location = $this->menuLocations->variantLocation($base, '' === $variant ? null : $variant);

        return null !== $location && !$this->menuExtension->getMenuBlocks($location)->isEmpty() ? $location : $base;
    }
}
