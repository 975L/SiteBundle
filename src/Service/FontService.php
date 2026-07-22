<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Service;

use c975L\ConfigBundle\Service\ConfigServiceInterface;
use c975L\SiteBundle\Repository\FontRepository;
use c975L\UiBundle\Contract\FontProviderInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

// Reads the font-family names declared via @font-face in the CSS file pointed to by the "site-fonts-face-file"
// config (defaults to assets/styles/_fonts.css, see scaffold/assets/styles/_fonts.css), merged with the names
// admin-uploaded via FontCrudController (see Font/FontCssListener) - together they're what ConfigBundle's
// theme-font-family-* configs offer as a real <select> instead of free text - auto-discovered by UiBundle's
// FontProviderPass, no manual service tag needed
class FontService implements FontProviderInterface
{
    private const DEFAULT_FONTS_FACE_FILE = '/assets/styles/_fonts.css';

    public function __construct(
        private readonly ConfigServiceInterface $configService,
        private readonly FontRepository $fontRepository,
        #[Autowire('%kernel.project_dir%')]
        private readonly string $projectDir,
    ) {
    }

    public function getFonts(): array
    {
        $fonts = array_merge($this->getDeclaredFonts(), $this->fontRepository->findDistinctNames());

        $fonts = array_values(array_unique($fonts));
        sort($fonts);

        return $fonts;
    }

    // Fonts declared by the dev via @font-face in _fonts.css (or whatever "site-fonts-face-file" points to)
    private function getDeclaredFonts(): array
    {
        $path = $this->configService->get('site-fonts-face-file') ?: self::DEFAULT_FONTS_FACE_FILE;
        $fullPath = $this->projectDir . $path;

        if (!is_file($fullPath)) {
            return [];
        }

        $content = file_get_contents($fullPath) ?: '';
        // Strips /* ... */ comments first, so a commented-out example or an old declaration kept for
        // reference doesn't get offered as a real, unusable font choice
        $content = preg_replace('#/\*.*?\*/#s', '', $content) ?? '';

        $fonts = [];
        if (preg_match_all('/@font-face\s*\{([^}]*)\}/i', $content, $blocks)) {
            foreach ($blocks[1] as $block) {
                if (preg_match('/font-family\s*:\s*["\']?([^"\';]+?)["\']?\s*;/i', $block, $match)) {
                    $fonts[] = trim($match[1]);
                }
            }
        }

        return $fonts;
    }
}
