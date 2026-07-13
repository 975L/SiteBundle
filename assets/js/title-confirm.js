/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from '@hotwired/stimulus';

// Confirms with the user before letting them edit the field, since it will also change the page's slug
// (see PageCrudController). Reuses EasyAdmin's own action-confirmation modal (unconditionally rendered
// on every crud/edit page, see vendor/easycorp/easyadmin-bundle .../_action_confirmation_modal.html.twig)
// instead of a native confirm() - same look as the "move to trash" confirmation, and window.bootstrap is
// already exposed globally by EasyAdmin's own admin.js.
export default class extends Controller {
    static values = { message: String };

    confirm() {
        if (this.element.dataset.confirmed) {
            return;
        }
        this.element.blur();

        const modalEl = document.querySelector('#modal-action-confirmation');
        const modalTitle = document.querySelector('#action-confirmation-title');
        const modalButton = document.querySelector('#modal-action-confirmation-button');
        if (!modalEl || !modalTitle || !modalButton || !window.bootstrap) {
            return;
        }

        modalTitle.textContent = this.messageValue;
        modalButton.addEventListener('click', () => {
            this.element.dataset.confirmed = '1';
            this.element.focus();
        }, { once: true });

        window.bootstrap.Modal.getOrCreateInstance(modalEl).show();
    }
}
