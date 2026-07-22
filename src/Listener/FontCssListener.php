<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Listener;

use c975L\SiteBundle\Entity\Font;
use c975L\SiteBundle\Repository\FontRepository;
use c975L\UiBundle\CacheWarmer\StylesheetCacheWarmer;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;

// Fires for any Font flushed through the EntityManager and regenerates public/bundles/build/site-fonts-uploaded.css
// from every currently uploaded Font - same "compiled from DB, single source of truth" pattern as ThemeVariablesCssListener,
// but for the admin-uploaded fonts rather than the dev-declared ones in _fonts.css (see FontService, which offers both
// to the "font" kind config selects). Also a CacheWarmer for the same reason: rows persisted/restored without firing
// a fresh Doctrine event must still produce an up-to-date file on cache:warmup/cache:clear.
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
class FontCssListener implements CacheWarmerInterface
{
    public function __construct(
        private readonly FontRepository $fontRepository,
        private readonly StylesheetCacheWarmer $stylesheetCacheWarmer,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {}

    public function postPersist(PostPersistEventArgs $args): void
    {
        $this->regenerateIfFont($args->getObject());
    }

    public function postUpdate(PostUpdateEventArgs $args): void
    {
        $this->regenerateIfFont($args->getObject());
    }

    public function postRemove(PostRemoveEventArgs $args): void
    {
        $this->regenerateIfFont($args->getObject());
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

    private function regenerateIfFont(object $entity): void
    {
        if (!$entity instanceof Font) {
            return;
        }

        $this->regenerate();
    }

    // Rewrites the whole file from every current Font row, not just the one that changed
    private function regenerate(): void
    {
        $blocks = [];
        foreach ($this->fontRepository->findAllOrdered() as $font) {
            $format = $font->getFormat();
            if (null === $font->getFilename() || null === $format) {
                continue;
            }

            $blocks[] = sprintf(
                "@font-face {\n    font-family: \"%s\";\n    src: url(\"/%s\") format(\"%s\");\n    font-weight: %s;\n    font-style: %s;\n    font-display: swap;\n}\n",
                str_replace('"', '\\"', $font->getName() ?? ''),
                $font->getFilename(),
                $format,
                // A variable font's real axis range is hidden behind its .woff2 Brotli encoding - declaring the full
                // 100-900 span is always safe, the browser clamps to what the file's own fvar table actually supports
                $font->isVariable() ? '100 900' : (string) $font->getWeight(),
                $font->getStyle(),
            );
        }

        $buildDir = $this->projectDir . '/public/bundles/build';
        if (!is_dir($buildDir) && !@mkdir($buildDir, 0775, true) && !is_dir($buildDir)) {
            throw new \RuntimeException(sprintf('Unable to create the "%s" directory.', $buildDir));
        }

        $path = $buildDir . '/site-fonts-uploaded.css';
        $tmpPath = $path . '.' . uniqid('', true) . '.tmp';
        if (false === @file_put_contents($tmpPath, implode("\n", $blocks)) || !@rename($tmpPath, $path)) {
            throw new \RuntimeException(sprintf('Unable to write "%s".', $path));
        }

        // In prod, the real site never reads this file directly - it links UiBundle's concatenated bundles/build/site.css instead (see StylesheetExtension), which is otherwise only rebuilt on cache:warmup. Without this, uploading a font would regenerate site-fonts-uploaded.css but the live site would keep serving the previous version until the next deploy/warmup
        $this->stylesheetCacheWarmer->compileAll();
    }
}
