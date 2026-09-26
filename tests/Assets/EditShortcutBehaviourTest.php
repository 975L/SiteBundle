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

// assets/js/edit-shortcut.js, three quick clicks on the footer leading to the login form and back to the page being read. The navigation itself is cancelled through the "open" event, the shared tab having to stay on the fixture
#[Group('browser')]
class EditShortcutBehaviourTest extends JsCase
{
    public function testThreeClicksLeadToTheLoginFormBackToThePage(): void
    {
        $this->assertSame(
            '/login?_target_path=%2Fpages%2Fateliers%3Fpage%3D2',
            $this->clicks('#copyright', 3),
            'Three clicks on the footer do not lead to the login form and back to the page being read.'
        );
    }

    // Two clicks select a word, which is what anyone does to copy the site name
    public function testTwoClicksLeadNowhere(): void
    {
        $this->assertNull($this->clicks('#copyright', 2), 'Two clicks on the footer already lead to the login form.');
    }

    // A footer link keeps its own job, whatever the number of clicks
    public function testClicksOnALinkLeadNowhere(): void
    {
        $this->assertNull($this->clicks('#legal', 3), 'Clicks on a footer link lead to the login form.');
    }

    // The url the "open" event carried after $count clicks on $selector, null when it was not dispatched
    private function clicks(string $selector, int $count): ?string
    {
        return $this->observe(
            '<footer data-controller="edit-shortcut">
                <div class="menu-item"><a id="legal" class="menu-link" href="#legal">Mentions</a></div>
                <div class="menu-item"><span id="copyright" class="menu-label">© 2026</span></div>
            </footer>',
            ['edit-shortcut' => 'edit-shortcut'],
            sprintf('let url = null;
                root.querySelector("footer").addEventListener("edit-shortcut:open", (event) => { event.preventDefault(); url = event.detail.url; });
                for (let i = 0; i < %d; i++) { root.querySelector(%s).click(); }
                history.replaceState(null, "", window.__servedPath);
                return url;', $count, json_encode($selector)),
            ['before' => 'window.__servedPath = location.pathname + location.search; history.replaceState(null, "", "/pages/ateliers?page=2");']
        );
    }
}
