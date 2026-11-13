/**
 * Commission preview for partner price inputs (SRS 8.5): an empty partner price field shows
 * "Commission 15% → €42.50" computed from its sibling retail field.
 *
 * <input data-retail-for="key"> and <input data-partner-for="key" data-commission="15">
 */
const euro = new Intl.NumberFormat('en-IE', { style: 'currency', currency: 'EUR' });

function refresh(scope) {
    scope.querySelectorAll('[data-partner-for]').forEach((partner) => {
        const key = partner.dataset.partnerFor;
        const retail = scope.querySelector(`[data-retail-for="${CSS.escape(key)}"]`) ?? document.querySelector(`[data-retail-for="${CSS.escape(key)}"]`);
        const commission = parseFloat(partner.dataset.commission ?? '0');
        const value = parseFloat(retail?.value ?? '');

        partner.placeholder = Number.isFinite(value) && value > 0 && commission > 0
            ? `Commission ${commission}% → ${euro.format(value * (1 - commission / 100))}`
            : 'Rule';
    });
}

export function initUnitOptions() {
    refresh(document);
    document.addEventListener('input', (event) => {
        if (event.target.matches('[data-retail-for]')) {
            refresh(document);
        }
    });
    document.addEventListener('repeater:added', () => refresh(document));
}
