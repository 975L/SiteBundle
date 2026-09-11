/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// The language menu is a GET form, so it works with no javascript at all: pick a language, press the button (see components/General/Languages.html.twig). This is the enhancement - the choice is sent as soon as it is made, and the button that would then be a second click goes away
export default class extends Controller {
    static targets = ["submit"];

    connect() {
        if (this.hasSubmitTarget) {
            this.submitTarget.hidden = true;
        }
    }

    // requestSubmit() rather than submit(): it goes through the form's own validation and submit event, which is what Turbo listens to
    submit() {
        if (this.element.requestSubmit) {
            this.element.requestSubmit();

            return;
        }

        this.element.submit();
    }
}
