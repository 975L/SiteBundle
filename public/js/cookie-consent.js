/**
 * CookieConsent Controller
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2024 975L <contact@975l.com>
 */
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    connect() {
        document.addEventListener("DOMContentLoaded", this.onDomContentLoaded.bind(this));
        window.addEventListener("load", () => {
            window.cookieconsent.initialise({
                palette: {
                    popup: {
                        background: "#a7a7a7"
                    },
                    button: {
                        background: "#f1d600"
                    }
                },
                content: {
                    message: this.element.dataset.message,
                    dismiss: this.element.dataset.dismiss || undefined,
                    link: this.element.dataset.link || undefined,
                    href: this.element.dataset.href || undefined
                }
            });
        })
    }

    onDomContentLoaded() {
        // https://cookieconsent.insites.com
        this.loadScript("https://cdnjs.cloudflare.com/ajax/libs/cookieconsent2/3.1.0/cookieconsent.min.js");
    }

    loadScript(src, callback) {
        const script = document.createElement("script");
        script.src = src;
        script.onload = callback;
        document.head.appendChild(script);
    }
}