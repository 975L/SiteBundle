/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    static values = {
        message: String,
        accept: String,
        reject: String,
        policyUrl: String,
        policyLabel: String,
        lang: { type: String, default: "fr" },
    };

    connect() {
        // https://cookieconsent.orestbida.com/ - CSS+JS loaded here (not via a static bundle stylesheet list) so the CDN is only contacted on pages that actually render this component, i.e. sites with `site-enable-cookie-consent` on
        this.loadStylesheet("https://cdn.jsdelivr.net/npm/vanilla-cookieconsent@3.1.0/dist/cookieconsent.css");
        this.loadScript("https://cdn.jsdelivr.net/npm/vanilla-cookieconsent@3.1.0/dist/cookieconsent.umd.js", () => {
            if (!window.CookieConsent) {
                return;
            }

            const consentModal = {
                title: this.messageValue,
                description: this.messageValue,
                acceptAllBtn: this.acceptValue,
                acceptNecessaryBtn: this.rejectValue,
            };
            if (this.hasPolicyUrlValue && this.policyUrlValue) {
                consentModal.footer = `<a href="${this.policyUrlValue}" target="_blank" rel="noopener">${this.policyLabelValue}</a>`;
            }

            // A single non-essential category ("content") on purpose - covers any third-party embed (e.g. c975l/ui-bundle's video_iframe block, which reacts to window.CookieConsent on its own, see its README) - matches the binary Accept/Reject UI below, no preferences panel to build or maintain
            window.CookieConsent.run({
                categories: {
                    necessary: {
                        enabled: true,
                        readOnly: true,
                    },
                    content: {},
                },
                guiOptions: {
                    consentModal: {
                        layout: "bar inline",
                        position: "bottom",
                    },
                },
                language: {
                    default: this.langValue,
                    translations: {
                        [this.langValue]: { consentModal },
                    },
                },
            });
        });
    }

    loadScript(src, callback) {
        const script = document.createElement("script");
        script.src = src;
        script.onload = callback;
        document.head.appendChild(script);
    }

    loadStylesheet(href) {
        const link = document.createElement("link");
        link.rel = "stylesheet";
        link.href = href;
        document.head.appendChild(link);
    }
}
