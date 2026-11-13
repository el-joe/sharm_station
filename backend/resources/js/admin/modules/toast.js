import { escapeHtml } from './http.js';

const TONES = {
    success: 'border-success/30 bg-white text-ink-900 [&_.dot]:bg-success',
    error: 'border-error/30 bg-white text-ink-900 [&_.dot]:bg-error',
    info: 'border-info/30 bg-white text-ink-900 [&_.dot]:bg-info',
};

function container() {
    let stack = document.getElementById('toast-stack');

    if (!stack) {
        stack = document.createElement('div');
        stack.id = 'toast-stack';
        stack.className = 'pointer-events-none fixed inset-x-4 top-4 z-[100] flex flex-col items-end gap-2 sm:left-auto sm:right-6 sm:top-6';
        stack.setAttribute('aria-live', 'polite');
        document.body.append(stack);
    }

    return stack;
}

export function toast(message, type = 'success') {
    const item = document.createElement('div');
    item.setAttribute('role', 'status');
    item.className = `pointer-events-auto flex w-full max-w-sm items-start gap-3 rounded-card border px-4 py-3 text-sm shadow-lg transition ${TONES[type] ?? TONES.info}`;
    item.innerHTML = `<span class="dot mt-1.5 size-2 shrink-0 rounded-full"></span><p class="flex-1">${escapeHtml(message)}</p><button type="button" class="text-ink-500 hover:text-ink-900" aria-label="Dismiss">&times;</button>`;
    item.querySelector('button').addEventListener('click', () => item.remove());
    container().append(item);
    setTimeout(() => {
        item.classList.add('opacity-0');
        setTimeout(() => item.remove(), 300);
    }, 5000);
}

/** Flash messages rendered by the server as <template data-toast="success">text</template>. */
export function initToasts() {
    document.querySelectorAll('template[data-toast]').forEach((template) => {
        toast(template.content.textContent.trim(), template.dataset.toast);
        template.remove();
    });
}
