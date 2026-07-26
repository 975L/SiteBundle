<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Twig;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Entity\Font;
use c975L\SiteBundle\Repository\FontRepository;
use Symfony\Contracts\Cache\CacheInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

// The @font-face rules of the admin-uploaded fonts live inside the concatenated bundles/build/site.css (see
// FontCssListener), so the browser only discovers the .woff2 files once that stylesheet has been downloaded AND
// parsed - two serialized round-trips before the first glyph is even requested. Emitting a <link rel="preload">
// in the <head> lets the preload scanner start those downloads immediately, in parallel with the stylesheet.
class FontPreloadExtension extends AbstractExtension
{
    // The computed preloads are cached under this key, deleted by FontCssListener/ThemeVariablesCssListener - the two
    // places that already fire on a Font or a "theme" Config change, so nothing can go stale without them knowing
    public const CACHE_KEY = 'c975l_site.font_preloads';

    // Only the families the theme actually applies - preloading a font that the current theme doesn't use costs a
    // request and competes for bandwidth with the ones it does
    private const FONT_FAMILY_SLUGS = [
        'theme-font-family-title',
        'theme-font-family-body',
        'theme-font-family-accent',
    ];

    // Preloading is a priority hint, and everything is high-priority means nothing is. Two covers the usual
    // title + body pair; anything beyond that is better left to the normal @font-face discovery.
    private const MAX_PRELOADS = 2;

    // Derived from the extension rather than from Font::getMimeType() (upload-time detection commonly reports
    // "application/octet-stream" for a .woff2) or Font::getFormat() (returns the CSS keyword "truetype", not a
    // MIME type). A "type" the browser doesn't recognise makes it skip the preload entirely.
    private const MIME_TYPES = [
        'woff2' => 'font/woff2',
        'woff' => 'font/woff',
        'ttf' => 'font/ttf',
        'otf' => 'font/otf',
    ];

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly FontRepository $fontRepository,
        private readonly CacheInterface $cache,
    ) {}

    public function getFunctions(): array
    {
        return [
            new TwigFunction('font_preloads', [$this, 'getFontPreloads']),
        ];
    }

    /**
     * @return array<array{path: string, type: string}> Public path (no leading slash) and MIME type of each
     *                                                  font to preload - a "type" not matching what the server
     *                                                  sends makes the browser drop the preload altogether
     */
    public function getFontPreloads(): array
    {
        // Rendered in the <head> of every single front-end page, for a result that only changes when an admin uploads a
        // font or switches a theme family - cached rather than re-queried per family on each request
        return $this->cache->get(self::CACHE_KEY, fn (): array => $this->computeFontPreloads());
    }

    private function computeFontPreloads(): array
    {
        $families = $this->getUsedFamilies();
        if ([] === $families) {
            return [];
        }

        $preloads = [];
        foreach ($families as $family) {
            $font = $this->findPreloadableFont($family);
            if (null === $font) {
                continue;
            }

            $type = self::MIME_TYPES[strtolower(pathinfo($font->getFilename(), PATHINFO_EXTENSION))] ?? null;
            if (null === $type) {
                continue;
            }

            // Two families can resolve to the same file (a variable font used for both title and body)
            $preloads[$font->getFilename()] = ['path' => $font->getFilename(), 'type' => $type];
            if (count($preloads) >= self::MAX_PRELOADS) {
                break;
            }
        }

        return array_values($preloads);
    }

    // Config values may carry the generic fallback appended by ThemeVariablesCssListener ("Inter, sans-serif"),
    // so only the first family of the stack is the custom font actually worth preloading
    private function getUsedFamilies(): array
    {
        $families = [];
        foreach (self::FONT_FAMILY_SLUGS as $slug) {
            $value = trim((string) $this->configService->get($slug));
            if ('' === $value) {
                continue;
            }

            $first = trim(explode(',', $value)[0], " \t\n\r\0\x0B\"'");
            if ('' !== $first) {
                $families[$first] = true;
            }
        }

        return array_keys($families);
    }

    // The file the browser needs first for a given family: a variable font covers every weight on its own,
    // otherwise the upright regular is what body text renders with - bold/italic can load on demand
    private function findPreloadableFont(string $family): ?Font
    {
        $candidates = [];
        foreach ($this->fontRepository->findAllOrdered() as $font) {
            if (null === $font->getFilename() || 0 !== strcasecmp((string) $font->getName(), $family)) {
                continue;
            }

            if ('normal' !== $font->getStyle()) {
                continue;
            }

            if ($font->isVariable()) {
                return $font;
            }

            $candidates[$font->getWeight()] = $font;
        }

        return $candidates[400] ?? null;
    }
}
