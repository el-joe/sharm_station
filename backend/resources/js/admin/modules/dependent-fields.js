/**
 * <input type=checkbox data-disables="#tax_per_night"> disables the target while checked;
 * data-enables does the opposite. data-show-when="#field=value" toggles visibility.
 */
export function initDependentFields(root = document) {
    root.querySelectorAll('[data-disables], [data-enables]').forEach((toggle) => {
        if (toggle.dataset.dependentReady) {
            return;
        }

        toggle.dataset.dependentReady = '1';
        const sync = () => {
            const disables = toggle.hasAttribute('data-disables');
            const selector = toggle.dataset.disables ?? toggle.dataset.enables;
            const disabled = disables ? toggle.checked : !toggle.checked;
            document.querySelectorAll(selector).forEach((target) => {
                target.disabled = disabled;
                target.closest('[data-field]')?.classList.toggle('opacity-60', disabled);
            });
        };
        toggle.addEventListener('change', sync);
        sync();
    });

    root.querySelectorAll('[data-show-when]').forEach((element) => {
        if (element.dataset.showReady) {
            return;
        }

        element.dataset.showReady = '1';
        const rule = element.dataset.showWhen;
        const separator = rule.lastIndexOf('=');
        const selector = rule.slice(0, separator);
        const value = rule.slice(separator + 1);
        const inputs = document.querySelectorAll(selector);
        const sync = () => {
            const current = [...inputs].find((input) => (input.type === 'radio' ? input.checked : input.type !== 'hidden'));
            const active = current?.type === 'checkbox' ? String(current.checked) === value : current?.value === value;
            element.hidden = !active;
        };
        inputs.forEach((input) => input.addEventListener('change', sync));
        sync();
    });
}
