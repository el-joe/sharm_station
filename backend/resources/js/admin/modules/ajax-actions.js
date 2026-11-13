import { request } from './http.js';
import { toast } from './toast.js';

/**
 * <input type=checkbox data-ajax-toggle="url" data-field="hidden"> posts {field: checked} and reverts on error.
 * <button data-ajax-action="url" data-method="POST" [data-reload]> posts and shows the returned message.
 */
export function initAjaxToggles() {
    document.addEventListener('change', async (event) => {
        const toggle = event.target.closest('[data-ajax-toggle]');

        if (!toggle) {
            return;
        }

        const field = toggle.dataset.field ?? 'value';
        toggle.disabled = true;

        try {
            const result = await request(toggle.dataset.ajaxToggle, { method: 'PATCH', body: { [field]: toggle.checked } });
            toast(result.message ?? 'Saved.');
            toggle.closest('[data-toggle-scope]')?.classList.toggle('opacity-50', field === 'hidden' && toggle.checked);
        } catch (error) {
            toggle.checked = !toggle.checked;
            toast(error.message, 'error');
        } finally {
            toggle.disabled = false;
        }
    });
}

export function initAjaxActions() {
    document.addEventListener('click', async (event) => {
        const button = event.target.closest('[data-ajax-action]');

        if (!button) {
            return;
        }

        event.preventDefault();
        const label = button.innerHTML;
        button.disabled = true;
        button.textContent = button.dataset.busy ?? 'Working…';

        try {
            const result = await request(button.dataset.ajaxAction, { method: button.dataset.method ?? 'POST' });
            toast(result.message ?? 'Done.');

            if (button.hasAttribute('data-reload')) {
                setTimeout(() => location.reload(), 600);
            }
        } catch (error) {
            toast(error.message, 'error');
        } finally {
            button.disabled = false;
            button.innerHTML = label;
        }
    });
}
