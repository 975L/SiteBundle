/**
 * Confetti Controller
 * @author Laurent Marquet <laurent.marquet@laposte.net>
 * @copyright 2024 975L <contact@975l.com>
 */
import { Controller } from "@hotwired/stimulus";

export default class extends Controller {
    connect() {
        document.addEventListener("DOMContentLoaded", this.onDomContentLoaded.bind(this));
    }

    onDomContentLoaded() {
        // https://github.com/catdad/canvas-confetti
        this.loadScript("https://cdn.jsdelivr.net/npm/canvas-confetti@1.9.2/dist/confetti.browser.min.js", () => {
            confetti({ particleCount: 500, disableForReducedMotion: true });
        });
    }

    loadScript(src, callback) {
        const script = document.createElement("script");
        script.src = src;
        script.onload = callback;
        document.head.appendChild(script);
    }
}