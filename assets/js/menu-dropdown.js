/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// The navbar's dropdown is a <details>, which opens and closes on its own (see blocks/MenuDropdown.html.twig). This is the enhancement: closed on a click elsewhere or on Escape, as a menu is expected to, and always open below the navbar's breakpoint, where it is a heading among the burger's links
export default class extends Controller {
    static targets = ["details"];

    // Same breakpoint as _menu.scss's desktop styles
    static mobile = "(max-width: 767.98px)";

    connect() {
        this.media = window.matchMedia(this.constructor.mobile);
        this.sync = () => this.detailsTarget.open = this.media.matches;
        this.close = (event) => {
            if (this.media.matches || !this.detailsTarget.open) {
                return;
            }
            if ("keydown" === event.type ? "Escape" === event.key && this.element.contains(document.activeElement) : !this.element.contains(event.target)) {
                this.detailsTarget.open = false;
            }
            // Escape hands the focus back to the title it was opened from, as a menu button does
            if ("keydown" === event.type && !this.detailsTarget.open) {
                this.detailsTarget.querySelector("summary").focus();
            }
        };

        // On a phone the title is a heading, not a toggle: without this a tap would fold the links away again
        this.keepOpen = (event) => this.media.matches && event.preventDefault();

        this.sync();
        this.detailsTarget.querySelector("summary").addEventListener("click", this.keepOpen);
        this.media.addEventListener("change", this.sync);
        document.addEventListener("click", this.close);
        document.addEventListener("keydown", this.close);
    }

    disconnect() {
        this.detailsTarget.querySelector("summary").removeEventListener("click", this.keepOpen);
        this.media.removeEventListener("change", this.sync);
        document.removeEventListener("click", this.close);
        document.removeEventListener("keydown", this.close);
    }
}
