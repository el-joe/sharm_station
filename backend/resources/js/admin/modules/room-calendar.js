import { request } from './http.js';
import { toast } from './toast.js';

/**
 * Room availability month grid. Clicking an open/closed day toggles it through the JSON endpoint;
 * held and booked days are read-only.
 *
 * <div data-room-calendar data-url="...toggle endpoint..." data-day-class="shared day classes">  day buttons: [data-date][data-status]
 */
const STATUS_CLASSES = {
    open: 'border-ink-100 bg-white text-ink-900 hover:border-primary-500',
    closed: 'border-ink-300 bg-ink-100 text-ink-500 line-through hover:border-primary-500',
    held: 'border-brand-100 bg-brand-100 text-brand-700 cursor-not-allowed',
    booked: 'border-primary-100 bg-primary-100 text-primary-700 cursor-not-allowed',
};

export function initRoomCalendar() {
    document.querySelectorAll('[data-room-calendar]').forEach((calendar) => {
        calendar.addEventListener('click', async (event) => {
            const day = event.target.closest('button[data-date]');

            if (!day || !['open', 'closed'].includes(day.dataset.status) || day.disabled) {
                return;
            }

            const next = day.dataset.status === 'open' ? 'closed' : 'open';
            day.disabled = true;

            try {
                const result = await request(calendar.dataset.url, { method: 'POST', body: { dates: [day.dataset.date], status: next } });
                day.dataset.status = result.status;
                day.className = `${calendar.dataset.dayClass ?? ''} ${STATUS_CLASSES[result.status]}`;
                day.querySelector('[data-day-status]').textContent = result.status === 'closed' ? 'Closed' : '';
                day.setAttribute('aria-pressed', String(result.status === 'closed'));
            } catch (error) {
                toast(error.message, 'error');
            } finally {
                day.disabled = false;
            }
        });
    });
}
