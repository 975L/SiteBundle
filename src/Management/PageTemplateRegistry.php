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

// Merges the page templates contributed by every PageTemplateProviderInterface (SiteBundle's own
// shipped ones via SitePageTemplateProvider, plus any satellite bundle's) - same pattern as
// ConfigBundle's ThemePresetRegistry
class PageTemplateRegistry
{
    private array $templates;

    // @param iterable<PageTemplateProviderInterface> $providers
    public function __construct(iterable $providers)
    {
        $this->templates = ProviderMerger::merge($providers, fn (PageTemplateProviderInterface $provider) => $provider->getTemplates());
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
