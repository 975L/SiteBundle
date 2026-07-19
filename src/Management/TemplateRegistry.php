<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Management;

use c975L\ConfigBundle\Management\ProviderMerger;

// Merges the templates contributed by every TemplateProviderInterface (SiteBundle's own shipped ones via SiteTemplateProvider, plus any satellite bundle's or app's own) - same pattern as ConfigBundle's ThemePresetRegistry
class TemplateRegistry
{
    private array $templates;

    // @param iterable<TemplateProviderInterface> $providers
    public function __construct(iterable $providers)
    {
        $this->templates = ProviderMerger::merge($providers, fn (TemplateProviderInterface $provider) => $provider->getTemplates());
    }

    public function has(string $id): bool
    {
        return isset($this->templates[$id]);
    }

    public function get(string $id): ?array
    {
        return $this->templates[$id] ?? null;
    }

    public function all(): array
    {
        return $this->templates;
    }
}
