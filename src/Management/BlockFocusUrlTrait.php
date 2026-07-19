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

// Shared by SiteMediaUsageProvider/SiteBlockEditUrlProvider: builds an EasyAdmin edit URL for a
// Block's owning entity, optionally jumping straight to that block's own row on the form (see
// UiBundle's block-focus.js, which consumes the focusBlock param on the CrudController edit screen)
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
