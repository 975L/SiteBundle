<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Management;

use c975L\UiBundle\Entity\Block;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGeneratorInterface;

// Builds an EasyAdmin edit URL for a Block's owner, optionally jumping straight to that block's own row
trait BlockFocusUrlTrait
{
    private function blockFocusUrl(AdminUrlGeneratorInterface $adminUrlGenerator, string $crudControllerFqcn, ?int $entityId, ?Block $block = null): string
    {
        $urlGenerator = $adminUrlGenerator
            ->unsetAll()
            ->setController($crudControllerFqcn)
            ->setAction(Action::EDIT)
            ->setEntityId($entityId);

        if (null !== $block) {
            $urlGenerator->set('focusBlock', $block->getId());
        }

        return $urlGenerator->generateUrl();
    }
}
