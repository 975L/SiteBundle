/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// Opens a tutorial film (see templates/collection/TutorialItem.html.twig) in its dialog when the page anchor names it, and binds the film to its steps: the step playing is highlighted, a clicked step seeks the film to its start, a step with no start (film shot on an older parcours) is never highlighted
export default class extends Controller {
    static targets = ['dialog', 'video', 'step'];

    // A url shared with the film's anchor opens it on arrival
    connect() {
        this.route();
    }

    // Opens the dialog when the anchor is this film's, closes it when another film's is. The film starts on the click that led here, a browser refusing it (a url opened cold) leaving it on its poster
    route() {
        if (window.location.hash === `#${this.element.id}`) {
            if (!this.dialogTarget.open) {
                this.dialogTarget.showModal();
                this.videoTarget.play().catch(() => {});
            }
        } else if (this.dialogTarget.open) {
            this.dialogTarget.close();
        }
    }

    // Stops the film, and drops the anchor so the same card opens it again. replaceState, not a new history entry: the back button leaves the page rather than reopening the film
    closed() {
        this.videoTarget.pause();
        if (window.location.hash === `#${this.element.id}`) {
            history.replaceState(history.state, '', window.location.pathname + window.location.search);
        }
    }

    // A click on the backdrop lands on the dialog itself, its content being wrapped
    backdrop(event) {
        if (event.target === this.dialogTarget) {
            this.dialogTarget.close();
        }
    }

    // Highlights the last step started
    follow() {
        const now = this.videoTarget.currentTime;
        let current = null;

        this.stepTargets.forEach((step) => {
            if ('' !== step.dataset.start && Number(step.dataset.start) <= now) {
                current = step;
            }
        });

        this.stepTargets.forEach((step) => {
            step.classList.toggle('is-current', step === current);
        });
    }

    // Seeks the film to the clicked step's start, and plays it
    seek(event) {
        this.videoTarget.currentTime = Number(event.currentTarget.closest('li').dataset.start);
        this.videoTarget.play();
    }
}
