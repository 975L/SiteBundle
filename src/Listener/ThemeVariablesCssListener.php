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
use c975L\UiBundle\CacheWarmer\StylesheetCacheWarmer;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

// Fires for any Config flushed through the EntityManager, regardless of which controller or app code triggered the change - filters down to the "theme" group (colors/fonts editable by the admin in ThemeCrudController) and regenerates the compiled CSS file every time one of them changes, so there is a single source of truth (site_config) for both the site's stylesheet and the email layout (see ThemeVariablesExtension for the email side)
// Also a CacheWarmer: Config rows persisted before this listener existed (or restored from a backup) never fire a Doctrine event again on their own, so without this the compiled file could stay missing/stale until an admin happens to re-save a theme config - warming up on every cache:warmup/cache:clear guarantees it always reflects the current site_config, deploy or not
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
class ThemeVariablesCssListener implements CacheWarmerInterface
{
    // Theme-group configs that are never a CSS value themselves, so must stay out of the compiled :root block:
    // "theme-mode" drives the data-theme HTML attribute server-side (see layout.html.twig); "site-fonts-face-file"
    // is a PHP-side file path read by FontService, never consumed via var(--c975l-...) - also not "theme-"-prefixed,
    // which would otherwise corrupt the mechanical slug->variable mapping below
    private const EXCLUDED_SLUGS = ['theme-mode', 'site-fonts-face-file'];

    // Generic CSS family each font-family slug falls back to if the chosen custom font fails to load at runtime -
    // same defaults already baked into _variables.scss's var(..., fallback) for the "config left empty" case,
    // reused here for the "a font is set but its @font-face 404s/is slow" case
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
    ) {}

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
        $lines = [];
        foreach ($this->configRepository->findByGroup(Config::GROUP_THEME) as $config) {
            $value = $config->getValue();
            if (in_array($config->getSlug(), self::EXCLUDED_SLUGS, true) || null === $value || '' === $value) {
                continue;
            }

            // Mechanical mapping, e.g. "theme-color-primary" -> "--c975l-color-primary": no lookup table to maintain when a new theme variable is added to SiteBundle/config/configs-css.json
            $slug = $config->getSlug();
            $variable = '--c975l-' . (str_starts_with($slug, 'theme-') ? substr($slug, strlen('theme-')) : $slug);

            // A bare custom font name (from the new ChoiceField) gets its generic fallback appended. A value already
            // containing a comma is left untouched - it's either a generic keyword's own fallback-free case, or a
            // full stack an admin already typed by hand before this kind existed (e.g. '"Georgia", serif')
            $fallback = self::FONT_FALLBACKS[$config->getSlug()] ?? null;
            if (null !== $fallback && !str_contains($value, ',') && !in_array($value, Config::GENERIC_FONT_FAMILIES, true)) {
                $value .= ', ' . $fallback;
            }

            $lines[] = sprintf('    %s: %s;', $variable, $value);
        }

        $buildDir = $this->projectDir . '/public/bundles/build';
        if (!is_dir($buildDir) && !@mkdir($buildDir, 0775, true) && !is_dir($buildDir)) {
            throw new \RuntimeException(sprintf('Unable to create the "%s" directory.', $buildDir));
        }

        $css = [] === $lines ? '' : ":root {\n" . implode("\n", $lines) . "\n}\n";
        $path = $buildDir . '/site-theme.css';
        $tmpPath = $path . '.' . uniqid('', true) . '.tmp';
        if (false === @file_put_contents($tmpPath, $css) || !@rename($tmpPath, $path)) {
            throw new \RuntimeException(sprintf('Unable to write "%s".', $path));
        }

        // In prod, the real site never reads this file directly - it links UiBundle's concatenated bundles/build/site.css instead (see StylesheetExtension), which is otherwise only rebuilt on cache:warmup. Without this, an admin applying a preset would regenerate site-theme.css but still see the previous theme until the next deploy/warmup
        $this->stylesheetCacheWarmer->compileAll();
    }
}
