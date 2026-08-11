<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Entity;

use c975L\SiteBundle\Entity\Menu;
use PHPUnit\Framework\TestCase;

class MenuTest extends TestCase
{
    public function testStyleKeepsAKnownLayout(): void
    {
        $this->assertSame(Menu::STYLE_INLINE, new Menu()->setStyle(Menu::STYLE_INLINE)->getStyle());
        $this->assertSame(Menu::STYLE_BLOCK, new Menu()->setStyle(Menu::STYLE_BLOCK)->getStyle());
    }

    // The value ends up in a CSS class (see Footer.html.twig), so anything else would only ever name a rule no stylesheet carries - null instead, which reads as "the site's theme decides"
    public function testStyleFallsBackToNullForAnythingElse(): void
    {
        $this->assertNull(new Menu()->getStyle());
        $this->assertNull(new Menu()->setStyle('grid')->getStyle());
        $this->assertNull(new Menu()->setStyle('')->getStyle());
        $this->assertNull(new Menu()->setStyle(Menu::STYLE_INLINE)->setStyle(null)->getStyle());
    }
}
