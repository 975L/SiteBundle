/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from '@hotwired/stimulus';

// Keeps the "isIndexable" switch of an index row honest about what unpublishing did to it server-side (Page::unreferenceWhenUnpublished(): an unpublished page is never referenced) - what "sitemap-fields" does on the edit form, done here for the pair of toggles the list shows side by side. Left stale, that switch would keep showing "checked" over a row the database holds as false, and the next click would then send "false" for something already false - unchecking what it displays instead of what it holds. It's also disabled while the page is unpublished: checking it back would be undone by the server rule at once, and the display would be lying again. Mounted on the "isPublished" cell (see PageCrudController::configureFields(), through setHtmlAttribute() - EasyAdmin only renders a field's html attributes on the index <td>), the sibling cell found by the "data-column" EasyAdmin puts on each of them. Nothing is sent from here: unpublishing already carries the rule server-side, this only mirrors it
export default class extends Controller {
    connect() {
        this.published = this.element.querySelector('input[type="checkbox"]');
        this.indexable = this.element
            .closest('tr')
            ?.querySelector('[data-column="isIndexable"] input[type="checkbox"]');
        if (!this.published || !this.indexable) {
            return;
        }

        // Also on connect, not just on change: a page unpublished before this rule existed still holds isIndexable = true until its next save, and the list would show it as referenced when it no longer is
        this.sync();

        this.published.addEventListener('change', this.sync);
    }

    disconnect() {
        this.published?.removeEventListener('change', this.sync);
    }

    // Arrow function so it can be used as-is for both add/removeEventListener. Mirrors the new state right away, as optimistically as EasyAdmin's own toggle does - should its PATCH fail, it restores and disables the switch it owns, and this row is visibly broken either way
    sync = () => {
        const unpublished = !this.published.checked;
        if (unpublished) {
            this.indexable.checked = false;
        }

        this.indexable.disabled = unpublished;

        // The class EasyAdmin's own toggle uses when it disables itself, so a locked switch looks the same wherever it comes from
        this.indexable.closest('.ea-switch')?.classList.toggle('ea-switch-disabled', unpublished);
    };
}
