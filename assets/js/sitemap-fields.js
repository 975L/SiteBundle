/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from '@hotwired/stimulus';

// Locks the changeFrequency/priority fields when the page isn't indexable, since they'd then be meaningless (see PageCrudController). Mounted on the "isIndexable" row, not on the checkbox itself: EasyAdmin renders a BooleanField as a <twig:ea:Switch> component that drops the field's "attr". Deliberately not "disabled": a disabled control isn't submitted, so both values would be wiped on every single save of a non-indexable page (renaming a title on the seeded "creer-un-compte" would silently drop them). "readonly" keeps them submitted, and since it's a no-op on changeFrequency's <select>, interaction is blocked on top of it. Fields are matched by an id suffix rather than a full id, as EasyAdmin prefixes them with the entity name (eg. "Page_priority")
export default class extends Controller {
    static SUFFIXES = ['_changeFrequency', '_priority'];

    connect() {
        this.checkbox = this.element.querySelector('input[type="checkbox"]');
        if (!this.checkbox) {
            return;
        }

        this.toggle();

        // Listened for on the row, where the checkbox's own "change" bubbles up to
        this.element.addEventListener('change', this.toggle);
    }

    disconnect() {
        this.element.removeEventListener('change', this.toggle);
    }

    // Arrow function so it can be used as-is for both add/removeEventListener
    toggle = () => {
        const form = this.element.closest('form');
        if (!form) {
            return;
        }

        const locked = !this.checkbox.checked;
        this.constructor.SUFFIXES.forEach((suffix) => {
            const field = form.querySelector(`[id$="${suffix}"]`);
            if (!field) {
                return;
            }

            field.readOnly = locked;
            field.setAttribute('aria-disabled', locked ? 'true' : 'false');

            // What actually keeps the <select> from being changed, "readonly" having no effect on it - kept out of reach of the keyboard too
            field.style.pointerEvents = locked ? 'none' : '';
            field.tabIndex = locked ? -1 : 0;

            // Dims the whole row (label and help text included), not just the input
            const group = field.closest('.form-group');
            if (group) {
                group.style.opacity = locked ? '0.5' : '';
            }
        });
    };
}
