<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Service;

use c975L\UiBundle\Contract\BlockFixtureProviderInterface;

// Sample data for SiteBundle's block kinds, shown in UiBundle's block gallery
// (c975L\UiBundle\Controller\Management\BlockGalleryController).
// "articles_slider" and "menu_link" are deliberately left out: both only render something once
// resolved against a real Page/route ("articles_slider" pulls the "article" blocks of a real Page,
// "menu_link" resolves a real "page:ID"/"route:NAME" target) - there's no fake id/route this
// provider could fabricate that would actually render visible content, and both templates already
// render nothing at all for an unresolved target (see ArticlesSlider.html.twig/MenuLink.html.twig),
// so a fixture here would just show an empty box instead of the gallery's clearer "no example yet".
class BlockFixtureProvider implements BlockFixtureProviderInterface
{
    public function getFixtures(): array
    {
        return [
            'legal_model' => [
                '' => [
                    'model' => 'france/legal-notice',
                    'latestUpdate' => '2026-01-01',
                ],
            ],
            'twig_content' => [
                '' => [
                    'content' => '<p>Contenu Twig personnalisé, avec accès aux variables et fonctions Twig de l\'application.</p>',
                ],
            ],
        ];
    }
}
