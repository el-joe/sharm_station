import { toast } from './toast.js';

/**
 * Copy buttons: <button data-copy="#element-id"> copies the value (inputs) or text of the target,
 * <button data-copy-text="..."> copies the given text.
 */
export function initClipboard(root = document) {
    root.querySelectorAll('[data-copy], [data-copy-text]').forEach((button) => {
        button.addEventListener('click', async () => {
            const target = button.dataset.copy ? document.querySelector(button.dataset.copy) : null;
            const text = button.dataset.copyText ?? (target ? ('value' in target ? target.value : target.textContent) : '');

            try {
                await navigator.clipboard.writeText(text.trim());
                toast(button.dataset.copyMessage ?? 'Copied to the clipboard.');
            } catch {
                target?.select?.();
                toast('Copy failed: select the text and copy it manually.', 'error');
            }
        });
    });
}

/**
 * Native <dialog> helpers: [data-dialog-auto] opens on load (modal), [data-dialog-close] closes the parent dialog.
 */
export function initDialogs(root = document) {
    root.querySelectorAll('dialog[data-dialog-auto]').forEach((dialog) => dialog.showModal());
    root.querySelectorAll('[data-dialog-close]').forEach((button) => {
        button.addEventListener('click', () => button.closest('dialog')?.close());
    });
}

/**
 * Character counters: <input data-char-count maxlength="70"> followed by <span data-char-count-for="input-id">.
 */
export function initCharCounters(root = document) {
    root.querySelectorAll('[data-char-count-for]').forEach((counter) => {
        const input = document.getElementById(counter.dataset.charCountFor);

        if (!input) {
            return;
        }

        const update = () => {
            const max = Number(input.getAttribute('maxlength') || 0);
            counter.textContent = max ? `${input.value.length} / ${max}` : String(input.value.length);
        };

        input.addEventListener('input', update);
        update();
    });
}
