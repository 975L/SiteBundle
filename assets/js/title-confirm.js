/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from '@hotwired/stimulus';

// Confirms with the user before letting them edit the field, since it will also change the page's slug (see PageCrudController)
export default class extends Controller {
    static values = { message: String };

    confirm() {
        if (this.element.dataset.confirmed) {
            return;
        }

        if (confirm(this.messageValue)) {
            this.element.dataset.confirmed = '1';
        } else {
            this.element.blur();
        }
    }
}
