/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Folds the navbar into its burger whenever its links no longer fit on one line, whatever the screen: the breakpoint in _menu.scss only knows the width, not how many entries a site put in its bar, and a tablet used to get them over two or three lines. Read again whenever the bar or the room left to its links changes width, and once the fonts are in, a wider face taking more room
export default class extends Controller {
    static targets = ["items"];

    // Same breakpoint as _menu.scss's desktop styles: below it the burger is drawn anyway
    static desktop = "(min-width: 768px)";

    // Watches the bar's width and the room left to its links, and measures again once the fonts are in
    connect() {
        this.media = window.matchMedia(this.constructor.desktop);
        this.widths = new Map();
        this.observer = new ResizeObserver((entries) => {
            // Only a width decides: folding the bar changes its height, which would call this again for nothing
            let changed = false;
            for (const entry of entries) {
                const width = Math.round(entry.contentRect.width);
                if (width !== this.widths.get(entry.target)) {
                    this.widths.set(entry.target, width);
                    changed = true;
                }
            }

            // A frame later: folding the bar inside this callback resizes what it observes, which the browser reports as a "ResizeObserver loop" error
            if (changed) {
                requestAnimationFrame(() => this.element.isConnected && this.check());
            }
        });

        // The items too: a button the bar unveils (the PWA install one) takes room from the links without changing the bar's width
        this.observer.observe(this.element);
        this.observer.observe(this.itemsTarget);
        this.check();
        document.fonts?.ready.then(() => this.element.isConnected && this.check());
    }

    // Stops watching a bar taken out of the page
    disconnect() {
        this.observer.disconnect();
    }

    // Laid out unfolded and measured on a forced layout, so connecting never paints the unfolded bar - only a first paint made before the controller connects may, and a resize may keep the former state for one frame. Folded as soon as an entry starts below the first one, and always below the breakpoint, where the links sit in the burger anyway: menu-dropdown.js reads that one class
    check() {
        const wasCollapsed = this.element.classList.contains("menu--collapsed");
        this.element.classList.remove("menu--collapsed");

        let collapsed = true;
        if (this.media.matches) {
            const entries = [...this.itemsTarget.querySelectorAll(".blocks > *, .menu-items > :not(.blocks)")].filter((entry) => null !== entry.offsetParent);
            const firstBottom = entries.length > 0 ? entries[0].getBoundingClientRect().bottom : 0;
            collapsed = entries.some((entry) => entry.getBoundingClientRect().top >= firstBottom - 1);
        }

        this.element.classList.toggle("menu--collapsed", collapsed);
        if (collapsed !== wasCollapsed) {
            this.dispatch("change", { detail: { collapsed } });
        }
    }
}
