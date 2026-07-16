<?php
/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Service;

use c975L\SiteBundle\Service\ArticlesSliderCacheTagProvider;
use c975L\UiBundle\Entity\Block;
use PHPUnit\Framework\TestCase;

class ArticlesSliderCacheTagProviderTest extends TestCase
{
    public function testResolverReturnsPageTagForTheReferencedPage(): void
    {
        $resolvers = (new ArticlesSliderCacheTagProvider())->getCacheTagResolvers();
        $block = (new Block())->setKind('articles_slider')->setData(['pageId' => 5]);

        $this->assertSame(['page_5'], $resolvers['articles_slider']($block));
    }

    public function testResolverReturnsEmptyArrayWhenNoPageIdIsSet(): void
    {
        $resolvers = (new ArticlesSliderCacheTagProvider())->getCacheTagResolvers();
        $block = (new Block())->setKind('articles_slider')->setData([]);

        $this->assertSame([], $resolvers['articles_slider']($block));
    }
}
