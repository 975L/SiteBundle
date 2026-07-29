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
use c975L\SiteBundle\Listener\Trait\BuildFileWriterTrait;
use c975L\SiteBundle\Twig\FontPreloadExtension;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsDoctrineListener;
use Doctrine\ORM\Event\PostPersistEventArgs;
use Doctrine\ORM\Event\PostRemoveEventArgs;
use Doctrine\ORM\Event\PostUpdateEventArgs;
use Doctrine\ORM\Events;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpKernel\CacheWarmer\CacheWarmerInterface;
use Symfony\Contracts\Cache\CacheInterface;

// Regenerates the compiled @font-face stylesheet from every uploaded Font, on flush and on cache warmup
#[AsDoctrineListener(event: Events::postPersist)]
#[AsDoctrineListener(event: Events::postUpdate)]
#[AsDoctrineListener(event: Events::postRemove)]
class FontCssListener implements CacheWarmerInterface
{
    use BuildFileWriterTrait;

    public function __construct(
        private readonly FontRepository $fontRepository,
        private readonly StylesheetCacheWarmer $stylesheetCacheWarmer,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
        private readonly CacheInterface $cache,
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
        // The <head>'s preloads are computed from the same rows, so they go stale at the same moment
        $this->cache->delete(FontPreloadExtension::CACHE_KEY);

        $blocks = [];
        foreach ($this->fontRepository->findAllOrdered() as $font) {
            $rule = $this->fontFaceRule($font);
            if (null !== $rule) {
                $blocks[] = $rule;
            }
        }

        $this->writeBuildFile('site-fonts-uploaded.css', implode("\n", $blocks));

        // In prod, the real site never reads this file directly - it links UiBundle's concatenated bundles/build/site.css instead (see StylesheetExtension), which is otherwise only rebuilt on cache:warmup. Without this, uploading a font would regenerate site-fonts-uploaded.css but the live site would keep serving the previous version until the next deploy/warmup
        $this->stylesheetCacheWarmer->compileAll();
    }

    // One Font row as its @font-face rule, or null for a row with nothing to serve yet (no uploaded file, unknown format)
    private function fontFaceRule(Font $font): ?string
    {
        $format = $font->getFormat();
        if (null === $font->getFilename() || null === $format) {
            return null;
        }

        return sprintf(
            "@font-face {\n    font-family: \"%s\";\n    src: url(\"/%s\") format(\"%s\");\n    font-weight: %s;\n    font-style: %s;\n    font-display: swap;\n}\n",
            str_replace('"', '\\"', $font->getName() ?? ''),
            $font->getFilename(),
            $format,
            // The full 100-900 span is always safe, the browser clamping to what the file supports
            $font->isVariable() ? '100 900' : (string) $font->getWeight(),
            $font->getStyle(),
        );
    }
}
