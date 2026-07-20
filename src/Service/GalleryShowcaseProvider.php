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

// Shows "articles_slider" and "menu_link" in the block showcase (see UiBundle's GalleryShowcaseRegistry,
// consumed by the public block showcase) - neither has a BlockFixtureProviderInterface fixture (see
// BlockFixtureProvider's own comment for why: both only render something once resolved against a real
// Page/route). Rendered here instead, directly against the same underlying components/markup with
// made-up sample data, bypassing that resolution.
class GalleryShowcaseProvider implements GalleryShowcaseProviderInterface
{
    public function __construct(
        private readonly Environment $twig,
        private readonly TranslatorInterface $translator,
        private readonly BlockFixtureMediaAttacher $mediaAttacher,
    ) {
    }

    // Both stand in for their own block kind - same feature, just previewed here without the live
    // Page/route resolution the real render depends on. The showcase suppresses each kind's own regular
    // (empty) preview card once "kind" is set here, so neither shows up twice.
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

    // "articles_slider" ultimately just feeds a few articles' title/hook/image into the same Slider
    // component "slider" itself uses (see ArticlesSlider.html.twig) - reusing UiBundle's own showcase
    // placeholder images here too, same as the "slider"/"image" block previews. Drawn from
    // BlockFixtureMediaAttacher's rotating pool, so the 3 articles don't all show the same photo.
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

    // Same markup/classes as MenuLink.html.twig, with a made-up target/label instead of resolving a
    // real "page:ID"/"route:NAME" via menu_link_url()/menu_link_label() - see the class comment
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
