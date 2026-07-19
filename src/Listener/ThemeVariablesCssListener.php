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
    // Drives the data-theme HTML attribute server-side (see layout.html.twig), not a CSS value itself
    private const EXCLUDED_SLUG = 'theme-mode';

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
            if (self::EXCLUDED_SLUG === $config->getSlug() || null === $value || '' === $value) {
                continue;
            }

            // Mechanical mapping, e.g. "theme-color-primary" -> "--c975l-color-primary": no lookup table to maintain when a new theme variable is added to SiteBundle/config/configs-css.json
            $variable = '--c975l-' . substr($config->getSlug(), strlen('theme-'));
            $lines[] = sprintf('    %s: %s;', $variable, $value);
        }

        $buildDir = $this->projectDir . '/public/bundles/build';
        if (!is_dir($buildDir)) {
            mkdir($buildDir, 0775, true);
        }

        $css = [] === $lines ? '' : ":root {\n" . implode("\n", $lines) . "\n}\n";
        file_put_contents($buildDir . '/site-theme.css', $css);

        // In prod, the real site never reads this file directly - it links UiBundle's concatenated bundles/build/site.css instead (see StylesheetExtension), which is otherwise only rebuilt on cache:warmup. Without this, an admin applying a preset would regenerate site-theme.css but still see the previous theme until the next deploy/warmup
        $this->stylesheetCacheWarmer->compileAll();
    }
}
