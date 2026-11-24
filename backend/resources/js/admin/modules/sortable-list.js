import Sortable from 'sortablejs';
import { request } from './http.js';
import { toast } from './toast.js';

/**
 * Drag-to-reorder lists saved through a JSON endpoint.
 *
 * <div data-sortable-save="url" data-sortable-group="categories">
 *   <ul data-sortable-list="categories"> <li data-sortable-id="1"> … <ul data-sortable-list="items" data-parent-id="1"> <li data-sortable-id="7">
 *
 * Sends {categories: [ids], items: {parentId: [ids]}} after every drop.
 */
export function initSortableLists() {
    document.querySelectorAll('[data-sortable-save]').forEach((container) => {
        const save = async () => {
            const categories = [...container.querySelectorAll('[data-sortable-list="categories"] > [data-sortable-id]')].map((node) => Number(node.dataset.sortableId));
            const items = {};

            container.querySelectorAll('[data-sortable-list="items"]').forEach((list) => {
                items[list.dataset.parentId] = [...list.querySelectorAll(':scope > [data-sortable-id]')].map((node) => Number(node.dataset.sortableId));
            });

            try {
                const result = await request(container.dataset.sortableSave, { method: 'POST', body: { categories, items } });
                toast(result.message ?? 'Order saved.');
            } catch (error) {
                toast(error.message, 'error');
            }
        };

        container.querySelectorAll('[data-sortable-list]').forEach((list) => {
            Sortable.create(list, { handle: '[data-drag-handle]', draggable: '[data-sortable-id]', animation: 150, onEnd: save });
        });
    });
}
