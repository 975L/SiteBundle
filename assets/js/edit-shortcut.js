/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

const CLICKS = 3;
const DELAY = 600;

// Three quick clicks or taps on the footer, off its links, lead to the login form and back to the page being read, where the edit buttons are then shown. No link is written in the page, and the footer's html stays the same for every visitor so it can be cached once
export default class extends Controller {
    connect() {
        this.clicks = [];
        this.onClick = this.click.bind(this);
        this.element.addEventListener("click", this.onClick);
    }

    disconnect() {
        this.element.removeEventListener("click", this.onClick);
    }

    // Counts the clicks of the last DELAY ms, a link keeping its own job
    click(event) {
        if (event.target.closest("a, button")) {
            return;
        }

        const now = Date.now();
        this.clicks = this.clicks.filter((time) => now - time < DELAY).concat(now);
        if (this.clicks.length < CLICKS) {
            return;
        }

        this.clicks = [];
        const url = "/login?_target_path=" + encodeURIComponent(window.location.pathname + window.location.search);

        // Cancelable, so a site can send it elsewhere
        if (this.dispatch("open", { detail: { url }, cancelable: true }).defaultPrevented) {
            return;
        }

        window.location.assign(url);
    }
}
