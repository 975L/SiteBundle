/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";


// Marks the menu item of the page being read. Done here rather than in MenuLink.html.twig so the menu's html can be cached once for every page: the same rule the template applied - the link's own path, or a page under it
export default class extends Controller {
    connect() {
        const current = window.location.pathname;

        this.element.querySelectorAll("a.menu-link").forEach((link) => {
            const url = new URL(link.href, window.location.origin);
            // A link leaving the site, or pointing at a section of a page, never marks an item - the template compared the whole url, fragment included
            if (url.origin !== window.location.origin || "" !== url.hash) {
                return;
            }

            if (url.pathname === current || current.startsWith(url.pathname + "/")) {
                link.closest(".menu-item")?.classList.add("active");
                link.setAttribute("aria-current", "page");
            }
        });
    }
}
