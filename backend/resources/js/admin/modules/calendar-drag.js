import { request } from './http.js';
import { toast } from './toast.js';
import { confirmAction } from './confirm.js';

/**
 * Occupancy calendar drag and drop (SRS 12.3): drag a booked or held night of a booking onto another room and/or
 * date. The server checks the move first (dry run: availability, capacity, same building, new price), the change
 * is shown for confirmation, then saved; the page reloads to show the new occupancy.
 *
 * <div data-occupancy-move="move endpoint">  cells: a[data-move-booking][data-move-room][data-move-date][data-move-check-in],
 * drop targets: td[data-drop-room][data-drop-date]
 */
const DAY = 86400000;

function addDays(date, days) {
    const value = new Date(`${date}T00:00:00Z`);

    return new Date(value.getTime() + days * DAY).toISOString().slice(0, 10);
}

function daysBetween(from, to) {
    return Math.round((new Date(`${to}T00:00:00Z`) - new Date(`${from}T00:00:00Z`)) / DAY);
}

function describe(diff) {
    return diff.filter((row) => row.changed).map((row) => `${row.label}: ${row.before} → ${row.after}`).join('\n');
}

export function initCalendarDrag() {
    document.querySelectorAll('[data-occupancy-move]').forEach((grid) => {
        const url = grid.dataset.occupancyMove;
        let dragged = null;

        const clearTarget = () => grid.querySelectorAll('.is-drop-target').forEach((cell) => cell.classList.remove('is-drop-target', 'bg-primary-100'));

        grid.addEventListener('dragstart', (event) => {
            const cell = event.target.closest('a[data-move-booking]');

            if (!cell) {
                return;
            }

            dragged = { ...cell.dataset };
            event.dataTransfer.effectAllowed = 'move';
            event.dataTransfer.setData('text/plain', cell.dataset.moveBooking);
        });

        grid.addEventListener('dragend', () => {
            dragged = null;
            clearTarget();
        });

        grid.addEventListener('dragover', (event) => {
            const target = event.target.closest('td[data-drop-room]');

            if (!dragged || !target) {
                return;
            }

            event.preventDefault();
            event.dataTransfer.dropEffect = 'move';
            clearTarget();
            target.classList.add('is-drop-target', 'bg-primary-100');
        });

        grid.addEventListener('drop', async (event) => {
            const target = event.target.closest('td[data-drop-room]');

            if (!dragged || !target) {
                return;
            }

            event.preventDefault();
            clearTarget();
            const source = dragged;
            dragged = null;
            const offset = daysBetween(source.moveCheckIn, source.moveDate);
            const body = {
                booking_id: Number(source.moveBooking),
                from_room_id: Number(source.moveRoom),
                to_room_id: Number(target.dataset.dropRoom),
                check_in: addDays(target.dataset.dropDate, -offset),
            };

            if (body.from_room_id === body.to_room_id && body.check_in === source.moveCheckIn) {
                return;
            }

            try {
                const check = await request(url, { method: 'POST', body: { ...body, dry_run: true } });
                const changes = describe(check.diff) || 'The price does not change.';

                confirmAction(changes, async () => {
                    try {
                        const result = await request(url, { method: 'POST', body });
                        toast(result.message, 'success');
                        window.setTimeout(() => window.location.reload(), 600);
                    } catch (error) {
                        toast(error.message, 'error');
                    }
                }, { title: 'Move this booking?', okLabel: 'Move booking', tone: 'primary' });
            } catch (error) {
                toast(error.message, 'error');
            }
        });
    });
}
