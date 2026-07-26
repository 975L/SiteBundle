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
        label: String,
        policyUrl: String,
        policyLabel: String,
        lang: { type: String, default: "fr" },
        // Defaults match the vendored copies shipped in this bundle's public/ - the template passes the asset()-resolved (cache-busted) URLs, these are only the fallback when it doesn't
        stylesheet: { type: String, default: "/bundles/c975lsite/css/cookieconsent.css" },
        script: { type: String, default: "/bundles/c975lsite/js/cookieconsent.umd.js" },
    };

    connect() {
        // https://cookieconsent.orestbida.com/ (MIT) - served from this bundle, not from a CDN: a third party must not receive the visitor's IP before any consent is given, and it keeps the CSP free of an external script/style host. Still loaded here rather than in a static bundle stylesheet list, so it only costs the pages that actually render this component, i.e. sites with `site-enable-cookie-consent` on
        this.loadStylesheet(this.stylesheetValue);
        this.loadScript(this.scriptValue, () => {
            if (!window.CookieConsent) {
                return;
            }

            // "label" is the dialog's aria-label - without it (or a title, which the "bar inline" layout has no room for) the role="dialog" has no accessible name at all
            const consentModal = {
                label: this.labelValue,
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
