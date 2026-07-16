<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management;

// Implement to contribute page templates (see SitePageTemplateProvider for SiteBundle's own shipped
// ones, config/page-templates/*.json) - collected by PageTemplateRegistry, same pattern as ConfigBundle's
// ThemePresetProviderInterface
interface PageTemplateProviderInterface
{
    // Each entry: id => ['label' => string, 'domain' => string, 'blocks' => [['kind' => string, 'data' => array], ...]]
    // 'domain' is the translation domain owning 'label' (the provider's own bundle)
    public function getTemplates(): array;
}
