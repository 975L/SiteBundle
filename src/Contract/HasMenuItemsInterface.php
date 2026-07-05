<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Contract;

use c975L\SiteBundle\Entity\MenuItem;
use Doctrine\Common\Collections\Collection;

// Each entity that owns menu items must implement this interface (see HasMenuItemsTrait)
interface HasMenuItemsInterface
{
    public function getItems(): Collection;
    public function addItem(MenuItem $item): static;
    public function removeItem(MenuItem $item): static;
}
