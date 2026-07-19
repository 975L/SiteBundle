<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Twig;

// Holds the current request's collection item data (see PageController::resolveCollectionDetail()), if any - exposed to Twig as a stable global object (see CollectionItemExtension) rather than mutated via Environment::addGlobal(), which throws once the environment's extensions are already initialized (always true by the time a controller renders anything). Any block on a collection's detail Page can read it, not just one specially-recognized kind - e.g. "twig_content" (see TwigContent.html.twig).
class CollectionItemContext
{
    private ?array $data = null;

    public function set(?array $data): void
    {
        $this->data = $data;
    }

    public function get(): ?array
    {
        return $this->data;
    }
}
