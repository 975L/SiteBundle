/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from "@hotwired/stimulus";

// The "customize a legal model" screen. Reordering is ea-sortable.js', already mounted on <body> - the
// template just gives it the collection markup it looks for. What is left here is adding a section of one's
// own, and putting a unit back to the bundle's own wording.
export default class extends Controller {
    static targets = ["holder"];
    static values = { removeLabel: String };

    // A custom admin page, not an EasyAdmin CRUD form, so EasyAdmin's own collection JS never mounts here and
    // the plain data-prototype dance has to be done by hand
    add() {
        const holder = this.holderTarget;
        const index = Number(holder.dataset.index || 0);

        const card = document.createElement("div");
        card.className = "card mb-3";
        card.innerHTML = '<div class="card-body">' + holder.dataset.prototype.replace(/__name__/g, String(index)) + "</div>";
        card.querySelector(".card-body").appendChild(this.removeButton());

        holder.appendChild(card);
        holder.dataset.index = String(index + 1);

        // What trix-editor.js listens for: without it the inserted textarea stays a hidden <textarea>
        document.dispatchEvent(new CustomEvent("ea.collection.item-added", { detail: { newElement: card } }));
    }

    // The template renders this button on the sections already stored; a prototype one is given it here
    removeButton() {
        const button = document.createElement("button");
        button.type = "button";
        button.className = "btn btn-sm btn-outline-danger";
        button.dataset.action = "legal-model#remove";
        button.textContent = this.removeLabelValue;

        return button;
    }

    // Dropping the card is enough: the collection allows deletion, and an entry that no longer submits is
    // simply not part of the delta rebuilt on save
    remove(event) {
        const card = event.target.closest(".card");
        if (card) card.remove();
    }

    // Refills a unit with the text the bundle ships, held on the row since it was rendered. Saving then stores
    // nothing for it, which is what puts it back on the updatable path
    reset(event) {
        const item = event.target.closest(".field-collection-item");
        if (!item) return;

        const title = item.querySelector('input[name$="[title]"]');
        if (title) title.value = item.dataset.legalTitle || "";

        const textarea = item.querySelector("textarea[data-trix]");
        if (!textarea) return;

        const content = item.dataset.legalContent || "";
        textarea.value = content;

        // Trix keeps its own document: writing the textarea alone would be overwritten on the next keystroke
        const editor = item.querySelector("trix-editor");
        if (editor && editor.editor) {
            editor.editor.loadHTML(content);
        }
    }
}
