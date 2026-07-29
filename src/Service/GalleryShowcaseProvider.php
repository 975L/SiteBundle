<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Service;

use c975L\UiBundle\Contract\GalleryShowcaseProviderInterface;
use c975L\UiBundle\Service\BlockFixtureMediaAttacher;
use Symfony\Contracts\Translation\TranslatorInterface;
use Twig\Environment;

// Shows "articles_slider" and "menu_link" in the showcase, neither rendering anything until resolved against a real Page or route
class GalleryShowcaseProvider implements GalleryShowcaseProviderInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly BlockFixtureMediaAttacher $mediaAttacher,
    ) {
    }

    // Both stand in for their own kind, so the showcase suppresses that kind's own empty preview card
    public function getShowcases(): array
    {
        return [
            $this->translator->trans('label.gallery_showcase_articles_slider', [], 'site') => [
                'description' => $this->translator->trans('label.gallery_showcase_articles_slider_description', [], 'site'),
                'kind' => 'articles_slider',
                'variants' => ['' => $this->articlesSliderVariant()],
            ],
            $this->translator->trans('label.gallery_showcase_menu_link', [], 'site') => [
                'description' => $this->translator->trans('label.gallery_showcase_menu_link_description', [], 'site'),
                'kind' => 'menu_link',
                'variants' => ['' => $this->menuLinkVariant()],
            ],
        ];
    }

    // Reuses UiBundle's showcase placeholder pool, so the three articles don't share one photo
    private function articlesSliderVariant(): string
    {
        $slides = [];
        for ($i = 1; $i <= 3; ++$i) {
            $slides[] = [
                'image' => $this->mediaAttacher->nextPlaceholderImage()->setAlt("Article {$i}"),
                'title' => "Article {$i}",
                'text' => 'Extrait de l\'article, tronqué pour l\'aperçu...',
                'url' => '#',
            ];
        }

        return $this->twig->render('@c975LUi/components/Slider/Slider.html.twig', [
            'slides' => $slides,
            'id' => 'showcase-articles-slider-preview',
            'duration' => 3500,
            'imageLinksToUrl' => true,
        ]);
    }

    // Same markup as MenuLink.html.twig, with a made-up target instead of a resolved one
    private function menuLinkVariant(): string
    {
        return $this->twig->createTemplate(
            '<div class="menu-item"><a href="{{ url }}" class="menu-link"><span class="menu-label">{{ label }}</span></a></div>'
        )->render([
            'url' => '#',
            'label' => 'Accueil',
        ]);
    }
}
