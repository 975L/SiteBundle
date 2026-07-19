<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
namespace c975L\SiteBundle\Service;

use c975L\UiBundle\Contract\BlockCacheTagProviderInterface;
use c975L\UiBundle\Entity\Block;

// articles_slider resolves another Page's own "article" blocks live at render time (see ArticlesSlider.html.twig/site_page()) - tagging its cache entry with "page_{id}" lets ArticleBlockCacheInvalidationListener invalidate it whenever that page's articles change, something BlockCacheInvalidationListener alone can't do (it only knows about the directly changed Block/Media, not blocks elsewhere referencing it)
class ArticlesSliderCacheTagProvider implements BlockCacheTagProviderInterface
{
    public function getCacheTagResolvers(): array
    {
        return [
            'articles_slider' => static function (Block $block): array {
                $pageId = $block->getData()['pageId'] ?? null;

                return null !== $pageId ? ['page_' . $pageId] : [];
            },
        ];
    }
}
