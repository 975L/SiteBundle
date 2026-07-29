/*
 * (c) 2024: 975L <contact@975l.com>
 * (c) 2024: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// The password field behaviours moved to UiBundle's "password" controller, next to the form styling
export default class extends Controller {
    connect() {
        // Execute immediately for Turbo compatibility
        this.htmlBoilerPlate();
        this.externalLinks();
        this.smoothAnchorScroll();
        // Also listen for scroll events
        window.addEventListener("scroll", () => {
            this.backTopButton();
            this.pullDownButton();
        });
    }

    // h5bp - Avoids console errors
    htmlBoilerPlate() {
        if (!(window.console && console.log)) {
            (function () {
                const noop = () => {};
                const methods = [
                    "assert", "clear", "count", "debug", "dir", "dirxml", "error", "exception", "group",
                    "groupCollapsed", "groupEnd", "info", "log", "markTimeline", "profile", "profileEnd",
                    "markTimeline", "table", "time", "timeEnd", "timeStamp", "trace", "warn"
                ];
                const console = window.console = {};
                methods.forEach((method) => {
                    console[method] = noop;
                });
            })();
        }
    }

    // Replaces attributes rel="external" by target="_blank" in the links to avoid W3C validation problems
    externalLinks() {
        if (!document.getElementsByTagName) {
            return;
        }
        const anchors = document.getElementsByTagName("a");
        Array.from(anchors).forEach((anchor) => {
            if (anchor.getAttribute("href") && anchor.getAttribute("rel") === "external") {
                anchor.target = "_blank";
            }
        });
    }

    // Scrolled here rather than by Turbo, which treats a same-page "#anchor" as a full visit
    smoothAnchorScroll() {
        this.element.addEventListener("click", (event) => {
            const link = event.target.closest('a[href*="#"]');
            if (!link) {
                return;
            }

            const url = new URL(link.href, window.location.href);
            if (url.pathname !== window.location.pathname || url.hash === "") {
                return;
            }

            const target = document.getElementById(url.hash.slice(1));
            if (!target) {
                return;
            }

            event.preventDefault();
            history.pushState(null, "", url.hash);
            target.scrollIntoView({ behavior: "smooth", block: "start" });
        });
    }

    // Replaces carriage returns by <br>
    nl2br(str) {
        return str.replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, "$1<br>$2");
    }

    // Displays/Hides the backTop button
    backTopButton() {
        const amountScrolled = 300;
        const backTop = document.querySelector("a.backTop");

        if (backTop) {
            // Displays the backTop button
            if (window.scrollY > amountScrolled) {
                backTop.style.display = "block";
                backTop.classList.remove("fade-out");
                backTop.classList.add("fade-in");
            // Hides the backTop button
            } else {
                backTop.classList.remove("fade-in");
                backTop.classList.add("fade-out");
            }
        }
    }

    // Displays/Hides the pullDown button
    pullDownButton() {
        const amountScrolled = 300;
        const pullDown = document.querySelector("a.pullDown");

        if (pullDown) {
            // Displays the pullDown button
            if (window.scrollY + window.innerHeight + amountScrolled < document.body.scrollHeight) {
                pullDown.style.display = "block";
                pullDown.classList.remove("fade-out");
                pullDown.classList.add("fade-in");
            // Hides the pullDown button
            } else {
                pullDown.classList.remove("fade-in");
                pullDown.classList.add("fade-out");
            }
        }
    }
}
