<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Twig;

use Twig\Extension\AbstractExtension;
use Twig\Extension\GlobalsInterface;

// Registers "collectionItem" as a stable Twig global pointing at CollectionItemContext - the object
// itself never changes after Twig boots, only its internal state does (see CollectionItemContext), so
// this sidesteps Environment::addGlobal()'s "already initialized" restriction entirely
class CollectionItemExtension extends AbstractExtension implements GlobalsInterface
{
    public function __construct(private readonly CollectionItemContext $collectionItemContext)
    {
    }

    public function getGlobals(): array
    {
        return ['collectionItem' => $this->collectionItemContext];
    }
}
