/**
 * SeasonPriceEditor helpers: timeline preview bar of the next 12 months and per-row client hints.
 * Overlaps are validated on the server by SeasonPriceService; the message is shown inline.
 *
 * <div data-season-editor> rows contain [data-season-name], [data-season-from], [data-season-to],
 * [data-season-active], [data-season-yearly]; <div data-season-timeline> receives the bar.
 */
import { escapeHtml } from './http.js';

const COLOURS = ['bg-primary-700', 'bg-cta-600', 'bg-primary-500', 'bg-gold-500', 'bg-partner-600', 'bg-brand-700'];
const DAY = 86400000;

function parse(value) {
    if (!value) {
        return null;
    }

    const [year, month, day] = value.split('-').map(Number);

    return year ? Date.UTC(year, month - 1, day) : null;
}

function occurrences(from, to, yearly, windowStart, windowEnd) {
    if (!yearly) {
        return [[from, to]];
    }

    const result = [];
    const start = new Date(from);
    const end = new Date(to);
    const base = new Date(windowStart).getUTCFullYear();

    for (let offset = -1; offset <= 1; offset++) {
        const shift = base - start.getUTCFullYear() + offset;
        result.push([
            Date.UTC(start.getUTCFullYear() + shift, start.getUTCMonth(), start.getUTCDate()),
            Date.UTC(end.getUTCFullYear() + shift, end.getUTCMonth(), end.getUTCDate()),
        ]);
    }

    return result.filter(([a, b]) => b >= windowStart && a <= windowEnd);
}

function render(editor) {
    const timeline = editor.querySelector('[data-season-timeline]');

    if (!timeline) {
        return;
    }

    const today = new Date();
    const windowStart = Date.UTC(today.getUTCFullYear(), today.getUTCMonth(), 1);
    const windowEnd = Date.UTC(today.getUTCFullYear() + 1, today.getUTCMonth(), 0);
    const span = windowEnd - windowStart + DAY;
    const segments = [];

    editor.querySelectorAll('[data-repeater-row]').forEach((row, index) => {
        const from = parse(row.querySelector('[data-season-from]')?.value);
        const to = parse(row.querySelector('[data-season-to]')?.value);
        const active = row.querySelector('[data-season-active]')?.checked ?? true;
        const yearly = row.querySelector('[data-season-yearly]')?.checked ?? false;
        const name = row.querySelector('[data-season-name]')?.value || `Season ${index + 1}`;

        if (!from || !to || to < from || !active) {
            return;
        }

        occurrences(from, to, yearly, windowStart, windowEnd).forEach(([a, b]) => {
            const left = Math.max(a, windowStart);
            const right = Math.min(b, windowEnd);
            segments.push({ name, left: ((left - windowStart) / span) * 100, width: ((right - left + DAY) / span) * 100, colour: COLOURS[index % COLOURS.length] });
        });
    });

    const months = [];

    for (let i = 0; i < 12; i++) {
        const date = new Date(Date.UTC(today.getUTCFullYear(), today.getUTCMonth() + i, 1));
        months.push(`<span class="flex-1 text-center">${date.toLocaleString('en-GB', { month: 'short', timeZone: 'UTC' })}</span>`);
    }

    timeline.innerHTML = `
        <div class="relative h-10 overflow-hidden rounded-control bg-ink-100">
            ${segments.map((segment) => `<div class="absolute inset-y-0 flex items-center overflow-hidden border-r border-white px-2 text-xs font-semibold whitespace-nowrap text-white ${segment.colour}" style="left:${segment.left}%;width:${segment.width}%" title="${escapeHtml(segment.name)}">${escapeHtml(segment.name)}</div>`).join('')}
        </div>
        <div class="mt-2 flex text-[11px] text-ink-500">${months.join('')}</div>
        ${segments.length === 0 ? '<p class="mt-2 text-xs text-ink-500">No active seasons in the next 12 months: the base price applies.</p>' : ''}`;
}

export function initSeasonEditor() {
    document.querySelectorAll('[data-season-editor]').forEach((editor) => {
        render(editor);
        editor.addEventListener('change', () => render(editor));
        editor.addEventListener('input', () => render(editor));
        editor.addEventListener('repeater:change', () => render(editor));
    });
}
