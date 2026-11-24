import { request } from './http.js';
import { toast } from './toast.js';
import { confirmAction } from './confirm.js';

/**
 * Accommodation occupancy grid (rooms × nights).
 * Free night: first click starts a selection, a second click on the same room blocks the range (after confirmation).
 * Blocked night: click frees the run of blocked nights around it. Held and booked nights link to the booking.
 *
 * <div data-occupancy data-url="block endpoint" data-cell-classes='{"free": "...", "blocked": "..."}'>  cells: button[data-cell][data-room][data-date][data-status]
 */
export function initOccupancyCalendar() {
    document.querySelectorAll('[data-occupancy]').forEach((grid) => {
        const classes = JSON.parse(grid.dataset.cellClasses);
        const hint = document.querySelector('[data-occupancy-hint]');
        const defaultHint = hint?.textContent ?? '';
        let start = null;

        const cellsOfRoom = (room) => [...grid.querySelectorAll(`button[data-cell][data-room="${room}"]`)];
        const base = 'flex h-9 w-full items-center justify-center rounded-[6px] border border-ink-100';

        const paint = (cell, status) => {
            cell.dataset.status = status;
            cell.className = `${base} ${classes[status]}`;
            cell.innerHTML = status === 'blocked' ? '<span aria-hidden="true">✕</span>' : '';
            cell.setAttribute('aria-label', cell.getAttribute('aria-label').replace(/: \w+$/, `: ${status}`));
        };

        const clearSelection = () => {
            grid.querySelectorAll('.is-selected').forEach((td) => td.classList.remove('is-selected'));
            start = null;

            if (hint) {
                hint.textContent = defaultHint;
            }
        };

        const send = async (room, from, to, status) => {
            try {
                const result = await request(grid.dataset.url, { method: 'POST', body: { room_id: Number(room), from, to, status: status === 'blocked' ? 'closed' : 'open' } });
                cellsOfRoom(room).forEach((cell) => {
                    if (result.dates.includes(cell.dataset.date) && ['free', 'blocked'].includes(cell.dataset.status)) {
                        paint(cell, result.status);
                    }
                });
                toast(result.message, result.changed > 0 ? 'success' : 'info');
            } catch (error) {
                toast(error.message, 'error');
            }
        };

        grid.addEventListener('click', (event) => {
            const cell = event.target.closest('button[data-cell]');

            if (!cell || cell.disabled) {
                return;
            }

            const { room, date, status } = cell.dataset;

            if (status === 'blocked' && !start) {
                const cells = cellsOfRoom(room);
                let index = cells.indexOf(cell);
                let first = index;
                let last = index;

                while (first > 0 && cells[first - 1].dataset.status === 'blocked' && !cells[first - 1].disabled) first -= 1;
                while (last < cells.length - 1 && cells[last + 1].dataset.status === 'blocked') last += 1;

                const from = cells[first].dataset.date;
                const to = cells[last].dataset.date;
                confirmAction(from === to ? `Free the night of ${from}?` : `Free the blocked nights from ${from} to ${to}?`, () => send(room, from, to, 'free'),
                    { title: 'Unblock nights', okLabel: 'Unblock', tone: 'primary' });

                return;
            }

            if (status !== 'free') {
                return;
            }

            if (!start || start.room !== room) {
                clearSelection();
                start = { room, date };
                cell.parentElement.classList.add('is-selected');

                if (hint) {
                    hint.textContent = `From ${date}: now click the last night to block (Esc to cancel).`;
                }

                return;
            }

            const [from, to] = [start.date, date].sort();
            const selected = cellsOfRoom(room).filter((other) => other.dataset.date >= from && other.dataset.date <= to);
            selected.forEach((other) => other.parentElement.classList.add('is-selected'));
            confirmAction(`Block ${selected.length} night(s) from ${from} to ${to}? Booked or held nights are skipped.`, () => send(room, from, to, 'blocked').then(clearSelection),
                { title: 'Block nights', okLabel: 'Block', tone: 'primary' });
            start = null;
        });

        document.addEventListener('keydown', (event) => event.key === 'Escape' && clearSelection());
    });
}
