/*
 * (c) 2024: 975L <contact@975l.com>
 * (c) 2024: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    connect() {
        // Charger le script immédiatement
        this.loadScript(
            "https://cdnjs.cloudflare.com/ajax/libs/cookieconsent2/3.1.0/cookieconsent.min.js",
            () => {
                // Initialiser cookieconsent une fois le script chargé
                if (window.cookieconsent) {
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
                }
            }
        );
    }

    loadScript(src, callback) {
        const script = document.createElement("script");
        script.src = src;
        script.onload = callback;
        document.head.appendChild(script);
    }
}