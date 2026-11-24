import flatpickr from 'flatpickr';

/**
 * <input data-datepicker> (single), data-datepicker="range", data-datepicker="multiple".
 * Values are submitted as Y-m-d; the visible format is d/m/Y.
 *
 * Flatpickr hides the original input and shows a new "alt" input. The visible input takes over the
 * original id (so <label for> points at it), and keeps the accessible name and error/hint descriptions.
 */
export function initDatePickers(root = document) {
    root.querySelectorAll('input[data-datepicker]').forEach((input) => {
        if (input._flatpickr) {
            return;
        }

        const mode = input.dataset.datepicker || 'single';

        const picker = flatpickr(input, {
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

        labelAltInput(input, picker.altInput);
    });
}

function labelAltInput(input, altInput) {
    if (!altInput) {
        return;
    }

    const label = input.id ? document.querySelector(`label[for="${CSS.escape(input.id)}"]`) : null;
    const name = input.getAttribute('aria-label') || label?.textContent.replace(/\s*\*\s*$/, '').trim() || input.placeholder || input.name;

    if (input.id) {
        altInput.id = input.id;
        input.id = `${input.id}-value`;
    }

    if (name) {
        altInput.setAttribute('aria-label', name);
    }

    ['aria-describedby', 'aria-invalid'].forEach((attribute) => {
        if (input.hasAttribute(attribute)) {
            altInput.setAttribute(attribute, input.getAttribute(attribute));
        }
    });

    if (input.required) {
        altInput.setAttribute('aria-required', 'true');
    }

    input.removeAttribute('aria-label');
}
