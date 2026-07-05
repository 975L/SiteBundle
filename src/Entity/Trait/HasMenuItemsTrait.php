<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Entity\Trait;

use c975L\SiteBundle\Entity\MenuItem;
use Doctrine\Common\Collections\Collection;

// Provides $items collection methods for entities that own menu items (see HasMenuItemsInterface)
trait HasMenuItemsTrait
{
    private array $pendingItemRemovals = [];

    public function getItems(): Collection
    {
        return $this->items;
    }

    public function addItem(MenuItem $item): static
    {
        if (!$this->items->contains($item)) {
            $this->items->add($item);
            $item->setMenu($this);
        }

        return $this;
    }

    public function removeItem(MenuItem $item): static
    {
        if ($this->items->removeElement($item)) {
            if ($item->getMenu() === $this) {
                $item->setMenu(null);
            }
            $this->pendingItemRemovals[] = $item;
        }

        return $this;
    }

    public function popPendingItemRemovals(): array
    {
        $items = $this->pendingItemRemovals;
        $this->pendingItemRemovals = [];

        return $items;
    }
}
