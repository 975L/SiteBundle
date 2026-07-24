/*
 * (c) 2026: 975L <contact@975l.com>
 * (c) 2026: Laurent Marquet <laurent.marquet@laposte.net>
 *
 * This source file is subject to the MIT license that is bundled
 * with this source code in the file LICENSE.
 */
import { Controller } from '@hotwired/stimulus';

// Same icon as UiBundle's ea-sortable.js drag handle (block reordering) - duplicated rather than imported cross-bundle, in line with each c975L bundle staying self-contained. No width/height, deliberately - EasyAdmin's own icons don't set them either, relying on its global ".icon svg" CSS to size every icon consistently; hard-coding one here would make this the odd one out.
const MOVE_ICON = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" '
    + 'stroke-width="2" stroke-linecap="round" stroke-linejoin="round">'
    + '<polyline points="5 9 2 12 5 15"/><polyline points="9 5 12 2 15 5"/>'
    + '<polyline points="15 19 12 22 9 19"/><polyline points="19 9 22 12 19 15"/>'
    + '<line x1="2" y1="12" x2="22" y2="12"/><line x1="12" y1="2" x2="12" y2="22"/>'
    + '</svg>';

// Mounted automatically on <body> by controllers-admin.js - no layout override needed. Only ever finds rows on CollectionItemCrudController's own index (see collection_item_crud_index.html.twig, the only template that renders [data-collection] on each <tr>), so it's a no-op everywhere else in the back-office.
export default class extends Controller {
    connect() {
        const rows = [...this.element.querySelectorAll('table.datagrid tbody tr[data-id][data-collection]')];
        if (0 === rows.length) return;

        rows.forEach(row => this.addHandle(row));

        let dragging = null;

        this.element.addEventListener('dragstart', e => {
            const row = e.target.closest('tr[data-collection]');
            if (!row) { e.preventDefault(); return; }
            dragging = row;
            requestAnimationFrame(() => {
                row.classList.add('ui-dragging');
                row.style.opacity = '0.4';
                row.style.boxShadow = 'inset 0 0 0 2px var(--bs-primary,#0d6efd)';
            });
        });

        this.element.addEventListener('dragend', () => {
            if (!dragging) return;
            dragging.classList.remove('ui-dragging');
            dragging.style.opacity = '';
            dragging.style.boxShadow = '';
            dragging.removeAttribute('draggable');
            this.persist(dragging);
            dragging = null;
        });

        this.element.addEventListener('dragover', e => {
            if (!dragging) return;
            const row = e.target.closest('tr[data-collection]');
            if (!row || row.dataset.collection !== dragging.dataset.collection) return;
            e.preventDefault();

            const siblings = this.collectionSiblings(dragging.dataset.collection, dragging);
            const after = this.rowAfter(siblings, e.clientY);
            if (after) dragging.parentElement.insertBefore(dragging, after);
            else siblings[siblings.length - 1]?.insertAdjacentElement('afterend', dragging);
        });
    }

    collectionSiblings(collectionId, excluding) {
        return [...this.element.querySelectorAll(`tr[data-collection="${CSS.escape(collectionId)}"]`)]
            .filter(row => row !== excluding);
    }

    // A small grip prepended to the "position" cell, the only column whose value the drag actually changes - dragging is only armed on its mousedown, so the rest of the row keeps working normally (EasyAdmin's own default-row-action still opens the edit page on a plain click).
    addHandle(row) {
        const cell = row.querySelector('td[data-column="position"]');
        if (!cell) return;

        const handle = document.createElement('span');
        handle.className = 'ui-sort-handle';
        handle.style.cursor = 'grab';
        handle.style.marginRight = '.5rem';
        handle.innerHTML = `<span class="icon">${MOVE_ICON}</span>`;
        cell.prepend(handle);

        const startDrag = () => row.setAttribute('draggable', 'true');
        const endDrag = () => row.removeAttribute('draggable');
        handle.addEventListener('mousedown', startDrag);
        handle.addEventListener('mouseup', endDrag);
    }

    rowAfter(siblings, y) {
        return siblings.reduce((closest, sibling) => {
            const box = sibling.getBoundingClientRect();
            const offset = y - box.top - box.height / 2;
            if (offset < 0 && offset > closest.offset) return { offset, element: sibling };
            return closest;
        }, { offset: -Infinity }).element;
    }

    persist(row) {
        const ids = [...this.element.querySelectorAll(`tr[data-collection="${CSS.escape(row.dataset.collection)}"]`)]
            .map(sibling => parseInt(sibling.dataset.id, 10));

        // Keeps the visible number in sync immediately - the server-side position it now maps to would otherwise only show up after a manual reload
        ids.forEach((id, position) => {
            const cell = this.element.querySelector(`tr[data-id="${id}"] td[data-column="position"]`);
            const label = cell && [...cell.childNodes].find(node => node.nodeType === Node.TEXT_NODE);
            if (label) label.textContent = String(position);
        });

        fetch(row.dataset.reorderUrl, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ collectionGroup: row.dataset.collection, ids, _token: row.dataset.reorderToken }),
        });
    }
}
