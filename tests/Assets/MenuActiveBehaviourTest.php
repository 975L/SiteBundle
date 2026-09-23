<?php

/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */

namespace c975L\SiteBundle\Tests\Assets;

use PHPUnit\Framework\Attributes\Group;

// assets/js/menu-active.js, which marks the menu item of the page being read now that the menu's html is cached once for every page - the rule MenuLink.html.twig applied before: the link's own path, or a page under it
#[Group('browser')]
class MenuActiveBehaviourTest extends JsCase
{
    public function testTheLinkToThePageBeingReadIsMarked(): void
    {
        $item = $this->page('/pages/ateliers', 'here');

        $this->assertTrue($item['active'], 'The item of the page being read is not marked active.');
        $this->assertSame('page', $item['current'], 'The link to the page being read does not tell a screen reader it is the current one.');
    }

    // A page under a menu entry keeps that entry lit, the visitor still being in its section
    public function testALinkToAParentPathIsMarked(): void
    {
        $this->assertTrue($this->page('/pages/ateliers/stage', 'here')['active'], 'A page under a menu entry leaves that entry unmarked.');
    }

    // A path merely starting with the same letters is another page, a fragment is a section, and another host is another site
    public function testNoOtherLinkIsMarked(): void
    {
        foreach (['other', 'prefix', 'section', 'elsewhere'] as $id) {
            $item = $this->page('/pages/ateliers', $id);

            $this->assertFalse($item['active'], sprintf('The "%s" item is marked active.', $id));
            $this->assertNull($item['current'], sprintf('The "%s" link carries aria-current.', $id));
        }
    }

    // The fixture's page is read at $path, put back once the probe has read the item so the next scenario starts from the page it was served on
    /** @return array{active: bool, current: ?string} */
    private function page(string $path, string $id): array
    {
        return $this->observe(
            '<nav data-controller="menu-active">
                <div class="menu-item"><a id="here" class="menu-link" href="/pages/ateliers">Ateliers</a></div>
                <div class="menu-item"><a id="other" class="menu-link" href="/pages/contact">Contact</a></div>
                <div class="menu-item"><a id="prefix" class="menu-link" href="/pages/atelier">Atelier</a></div>
                <div class="menu-item"><a id="section" class="menu-link" href="/pages/ateliers#tarifs">Tarifs</a></div>
                <div class="menu-item"><a id="elsewhere" class="menu-link" href="https://example.org/pages/ateliers">Ailleurs</a></div>
            </nav>',
            ['menu-active' => 'menu-active'],
            sprintf('const link = root.querySelector("#%s");
                const item = { active: link.closest(".menu-item").classList.contains("active"), current: link.getAttribute("aria-current") };
                history.replaceState(null, "", window.__servedPath);
                return item;', $id),
            ['before' => sprintf('window.__servedPath = location.pathname; history.replaceState(null, "", %s);', json_encode($path))]
        );
    }
}
