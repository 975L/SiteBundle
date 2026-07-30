<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Listener;

use c975L\ConfigBundle\Entity\Config;
use c975L\ConfigBundle\Repository\ConfigRepository;
use c975L\SiteBundle\Listener\Trait\BuildFileWriterTrait;
use c975L\SiteBundle\Twig\FontPreloadExtension;
use c975L\UiBundle\CacheWarmer\StylesheetCacheWarmer;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Contracts\Cache\CacheInterface;

// Regenerates the compiled theme CSS whenever a "theme" Config is flushed, site_config being the single source of truth
// Also a CacheWarmer: rows restored from a backup fire no Doctrine event of their own
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
class ThemeVariablesCssListener implements CacheWarmerInterface
{
    use BuildFileWriterTrait;

    // Theme configs that are never a CSS value, so must stay out of the compiled :root block
    private const EXCLUDED_SLUGS = ['theme-mode'];

    // Generic fallback per font slug, for when a chosen custom font fails to load at runtime
    private const FONT_FALLBACKS = [
        'theme-font-family-title' => 'sans-serif',
        'theme-font-family-body' => 'sans-serif',
        'theme-font-family-accent' => 'monospace',
    ];

    public function __construct(
        private readonly ConfigRepository $configRepository,
        private readonly StylesheetCacheWarmer $stylesheetCacheWarmer,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly CacheInterface $cache,
    ) {
    }

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->regenerateIfThemeConfig($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->regenerateIfThemeConfig($args->getObject());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->regenerateIfThemeConfig($args->getObject());
    }

    public function isOptional(): bool
    {
        return true;
    }

    public function warmUp(string $cacheDir, ?string $buildDir = null): array
    {
        $this->regenerate();

        return [];
    }

    private function regenerateIfThemeConfig(object $entity): void
    {
        if (!$entity instanceof Config || Config::GROUP_THEME !== $entity->getGroup()) {
            return;
        }

        $this->regenerate();
    }

    // Rewrites the whole file from every current theme config, not just the one that changed
    private function regenerate(): void
    {
        // The <head>'s preloads are computed from the same rows, so they go stale at the same moment
        $this->cache->delete(FontPreloadExtension::CACHE_KEY);

        $lines = [];
        foreach ($this->configRepository->findByGroup(Config::GROUP_THEME) as $config) {
            $line = $this->variableLine($config);
            if (null !== $line) {
                $lines[] = $line;
            }
        }

        $this->writeBuildFile('site-theme.css', [] === $lines ? '' : ":root {\n" . implode("\n", $lines) . "\n}\n");

        // In prod, the real site never reads this file directly - it links UiBundle's concatenated bundles/build/site.css instead (see StylesheetExtension), which is otherwise only rebuilt on cache:warmup. Without this, an admin editing a "theme" config would regenerate site-theme.css but still see the previous theme until the next deploy/warmup
        $this->stylesheetCacheWarmer->compileAll();
    }

    // One config row as its ":root" custom property declaration, or null when the row isn't one - mechanical mapping, e.g. "theme-color-primary" -> "--c975l-color-primary": no lookup table to maintain when a new theme variable is added to SiteBundle/config/configs-css.json. The "theme-" prefix is what marks a config as a CSS value, so anything else in the group is skipped rather than compiled into a variable no stylesheet reads - that's also what keeps a slug this bundle no longer ships (a row left behind in site_config, nothing prunes them) out of the file
    private function variableLine(Config $config): ?string
    {
        $slug = $config->getSlug();
        $value = $config->getValue();
        if (null === $value || '' === $value || !str_starts_with($slug, 'theme-') || in_array($slug, self::EXCLUDED_SLUGS, true)) {
            return null;
        }

        return sprintf('    --c975l-%s: %s;', substr($slug, strlen('theme-')), $this->withFontFallback($slug, $value));
    }

    // A bare font name gets its generic fallback appended; a value already holding a comma is left alone
    private function withFontFallback(string $slug, string $value): string
    {
        $fallback = self::FONT_FALLBACKS[$slug] ?? null;
        if (null === $fallback || str_contains($value, ',') || in_array($value, Config::GENERIC_FONT_FAMILIES, true)) {
            return $value;
        }

        return $value . ', ' . $fallback;
    }
}
