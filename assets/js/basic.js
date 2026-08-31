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
    }

    // h5bp - Avoids console errors
    htmlBoilerPlate() {
        if (!(window.console && console.log)) {
            (() => {
                const noop = () => {};
                const methods = [
                    "assert", "clear", "count", "debug", "dir", "dirxml", "error", "exception", "group",
                    "groupCollapsed", "groupEnd", "info", "log", "markTimeline", "profile", "profileEnd",
                    "markTimeline", "table", "time", "timeEnd", "timeStamp", "trace", "warn"
                ];
                window.console = {};
                const console = window.console;
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

    // Replaces carriage returns by <br>
    nl2br(str) {
        return str.replace(/([^>\r\n]?)(\r\n|\n\r|\r|\n)/g, "$1<br>$2");
    }
}
