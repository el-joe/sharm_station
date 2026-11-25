/**
 * Generic repeatable rows (unit options, private tiers, seasons, itinerary days and items).
 *
 * <div data-repeater data-placeholder="__INDEX__" [data-sortable]>
 *   <div data-repeater-list> ...rows [data-repeater-row] with [data-repeater-remove], [data-drag-handle], [data-sort-input]... </div>
 *   <template data-repeater-template> row markup using __INDEX__ in names/ids </template>
 *   <button data-repeater-add>
 * </div>
 *
 * Nested repeaters are supported: the outer template simply contains the inner [data-repeater] with its own placeholder.
 */
let counter = Date.now();

function ownElements(repeater, selector) {
    return [...repeater.querySelectorAll(selector)].filter((element) => element.closest('[data-repeater]') === repeater);
}

export function renumber(repeater) {
    const list = ownElements(repeater, '[data-repeater-list]')[0];

    if (!list) {
        return;
    }

    [...list.children].filter((row) => row.matches('[data-repeater-row]')).forEach((row, index) => {
        row.querySelectorAll('[data-sort-input]').forEach((input) => {
            if (input.closest('[data-repeater-row]') === row) {
                input.value = index;
            }
        });
        row.querySelectorAll('[data-row-number]').forEach((label) => {
            if (label.closest('[data-repeater-row]') === row) {
                label.textContent = String(index + 1);
            }
        });
    });

    const empty = ownElements(repeater, '[data-repeater-empty]')[0];
    empty?.toggleAttribute('hidden', list.querySelector('[data-repeater-row]') !== null);
    repeater.dispatchEvent(new CustomEvent('repeater:change', { bubbles: true }));
}

function setup(repeater, boot) {
    if (repeater.dataset.repeaterReady) {
        return;
    }

    repeater.dataset.repeaterReady = '1';
    const list = ownElements(repeater, '[data-repeater-list]')[0];
    const template = ownElements(repeater, 'template[data-repeater-template]')[0];
    const placeholder = repeater.dataset.placeholder || '__INDEX__';

    ownElements(repeater, '[data-repeater-add]').forEach((button) => {
        button.addEventListener('click', () => {
            const index = `n${counter++}`;
            const html = template.innerHTML.replaceAll(placeholder, index);
            const fragment = document.createRange().createContextualFragment(html);
            const row = fragment.querySelector('[data-repeater-row]');
            list.append(fragment);
            row.querySelectorAll('[data-repeater]').forEach((nested) => setup(nested, boot));
            boot?.(row);
            renumber(repeater);
            row.querySelector('input:not([type=hidden]), select, textarea')?.focus();
            repeater.dispatchEvent(new CustomEvent('repeater:added', { bubbles: true, detail: { row } }));
        });
    });

    list.addEventListener('click', (event) => {
        const remove = event.target.closest('[data-repeater-remove]');

        if (!remove || remove.closest('[data-repeater]') !== repeater) {
            return;
        }

        remove.closest('[data-repeater-row]').remove();
        renumber(repeater);
    });

    if (repeater.hasAttribute('data-sortable')) {
        // SortableJS is loaded only on pages with sortable rows.
        import('sortablejs').then(({ default: Sortable }) => Sortable.create(list, {
            handle: '[data-drag-handle]',
            draggable: '[data-repeater-row]',
            animation: 150,
            onEnd: () => renumber(repeater),
        }));
    }

    renumber(repeater);
}

export function initRepeaters(boot) {
    document.querySelectorAll('[data-repeater]').forEach((repeater) => setup(repeater, boot));
}
