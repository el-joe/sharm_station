/**
 * Itinerary builder: days (sortable) with timeline items (sortable). Day numbers follow the visual order.
 */
export function initItinerary() {
    document.querySelectorAll('[data-itinerary]').forEach((itinerary) => {
        const sync = () => {
            itinerary.querySelectorAll(':scope [data-day-row]').forEach((day, index) => {
                const input = day.querySelector('[data-day-number]');

                if (input) {
                    input.value = index + 1;
                }

                day.querySelectorAll('[data-day-label]').forEach((label) => {
                    label.textContent = `Day ${index + 1}`;
                });
            });
        };

        itinerary.addEventListener('repeater:change', sync);
        sync();
    });
}
