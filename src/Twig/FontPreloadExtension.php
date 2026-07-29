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

// Preloads the uploaded fonts, whose @font-face rules the browser would otherwise only discover after parsing the stylesheet
class FontPreloadExtension extends AbstractExtension
{
    // Cached under this key, deleted by the two listeners that already fire on the changes that matter
    public const CACHE_KEY = 'c975l_site.font_preloads';

    // Only the families the theme applies: preloading an unused font competes with the ones in use
    private const FONT_FAMILY_SLUGS = [
        'theme-font-family-title',
        'theme-font-family-body',
        'theme-font-family-accent',
    ];

    // Two covers the usual title and body pair; beyond that, everything high-priority means nothing is
    private const MAX_PRELOADS = 2;

    // From the extension: the upload-time mime is often "application/octet-stream", and getFormat() returns a CSS keyword
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
        // Cached rather than re-queried per family, this renders in the <head> of every front-end page
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

    // A value may carry the appended generic fallback, so only the first family is the custom font
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

    // A variable font covers every weight; otherwise the upright regular is what body text renders with
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
