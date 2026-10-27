/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// The navbar's dropdown is a <details>, which opens and closes on its own (see blocks/MenuDropdown.html.twig). This is the enhancement: closed on a click elsewhere or on Escape, as a menu is expected to, and always open while the links sit in the burger, where it is a heading among them - once menu-collapse.js folded the bar, which it always does below the breakpoint. The "toggle" value keeps a dropdown a toggle on any screen: the languages beside the magnifier, which never go into the burger
export default class extends Controller {
    static targets = ["details"];
    static values = { toggle: Boolean };

    connect() {
        this.isHeading = () => !this.toggleValue && null !== this.element.closest(".menu--collapsed");
        this.sync = () => this.detailsTarget.open = this.isHeading();
        this.close = (event) => {
            if (this.isHeading() || !this.detailsTarget.open) {
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

        // In the burger the title is a heading, not a toggle: without this a tap would fold the links away again
        this.keepOpen = (event) => this.isHeading() && event.preventDefault();

        // A toggle starts closed and stays the visitor's to open, whatever the width
        if (!this.toggleValue) {
            this.sync();
            document.addEventListener("menu-collapse:change", this.sync);
        }
        this.detailsTarget.querySelector("summary").addEventListener("click", this.keepOpen);
        document.addEventListener("click", this.close);
        document.addEventListener("keydown", this.close);
    }

    disconnect() {
        this.detailsTarget.querySelector("summary").removeEventListener("click", this.keepOpen);
        document.removeEventListener("menu-collapse:change", this.sync);
        document.removeEventListener("click", this.close);
        document.removeEventListener("keydown", this.close);
    }
}
