import flatpickr from 'flatpickr';

/**
 * <input data-datepicker> (single), data-datepicker="range", data-datepicker="multiple".
 * Values are submitted as Y-m-d; the visible format is d/m/Y.
 */
export function initDatePickers(root = document) {
    root.querySelectorAll('input[data-datepicker]').forEach((input) => {
        if (input._flatpickr) {
            return;
        }

        const mode = input.dataset.datepicker || 'single';

        flatpickr(input, {
            mode,
            dateFormat: 'Y-m-d',
            altInput: true,
            altFormat: mode === 'single' ? 'd/m/Y' : 'd M Y',
            altInputClass: input.className,
            allowInput: mode === 'single',
            conjunction: ', ',
            locale: { firstDayOfWeek: 1, rangeSeparator: ' – ' },
            onChange: () => input.dispatchEvent(new Event('change', { bubbles: true })),
        });
    });
}
