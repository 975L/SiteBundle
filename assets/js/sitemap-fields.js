/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from '@hotwired/stimulus';

// Keeps the sitemap fields consistent with what's above them (see PageCrudController): unpublishing the page unchecks and locks "isIndexable" - an unpublished page is never referenced, a rule Page::unreferenceWhenUnpublished() enforces server-side whatever the entry point, mirrored here so it's seen before saving instead of discovered after - and a non-indexable page locks changeFrequency/priority, which would then be meaningless. Mounted on the "isIndexable" row, not on the checkbox itself: EasyAdmin renders a BooleanField as a <twig:ea:Switch> component that drops the field's "attr". Deliberately not "disabled": a disabled control isn't submitted, so the locked values would be wiped on every single save of a non-indexable page (renaming a title on the seeded "creer-un-compte" would silently drop them). "readonly" keeps them submitted, and since it's a no-op on changeFrequency's <select>, interaction is blocked on top of it. Fields are matched by an id suffix rather than a full id, as EasyAdmin prefixes them with the entity name (eg. "Page_priority")
export default class extends Controller {
    static SUFFIXES = ['_changeFrequency', '_priority'];

    connect() {
        this.checkbox = this.element.querySelector('input[type="checkbox"]');
        this.form = this.element.closest('form');
        if (!this.checkbox || !this.form) {
            return;
        }

        this.publishedCheckbox = this.form.querySelector('[id$="_isPublished"]');

        this.toggle();

        // Listened for on the form, not on this row: the "isPublished" switch sits in a row of its own, whose "change" never bubbles through this one
        this.form.addEventListener('change', this.toggle);
    }

    disconnect() {
        this.form?.removeEventListener('change', this.toggle);
    }

    // Arrow function so it can be used as-is for both add/removeEventListener
    toggle = () => {
        // Setting "checked" from script fires no "change" event, so this can't re-enter
        const unpublished = null !== this.publishedCheckbox && !this.publishedCheckbox.checked;
        if (unpublished) {
            this.checkbox.checked = false;
        }
        this.lock(this.checkbox, unpublished);

        const locked = unpublished || !this.checkbox.checked;
        this.constructor.SUFFIXES.forEach((suffix) => {
            const field = this.form.querySelector(`[id$="${suffix}"]`);
            if (!field) {
                return;
            }

            field.readOnly = locked;
            this.lock(field, locked);
        });
    };

    // Blocks and dims a field without disabling it
    lock(field, locked) {
        field.setAttribute('aria-disabled', locked ? 'true' : 'false');

        // Kept out of reach of the keyboard too
        field.tabIndex = locked ? -1 : 0;

        // Blocked and dimmed on the whole row (label and help text included) rather than on the input alone: "readonly" is a no-op on a <select>, and clicking a switch's own <label> toggles it just as well as clicking the input
        // A class rather than an inline style: EasyAdmin's layout puts a nonce on style-src, which makes any style written from JS a no-op. .ui-field-locked comes from UiBundle (management/_form-fields.scss)
        const group = field.closest('.form-group') ?? field;
        group.classList.toggle('ui-field-locked', locked);
    }
}
