/**
 * Delete / destructive confirmation modal.
 * Usage: <form data-confirm="Delete this trip?" ...> or <button data-confirm="..." form="...">.
 */
let modal;
let pending = null;

function build() {
    modal = document.createElement('div');
    modal.className = 'fixed inset-0 z-[90] hidden items-center justify-center bg-brand-navy-900/50 p-4';
    modal.setAttribute('role', 'dialog');
    modal.setAttribute('aria-modal', 'true');
    modal.innerHTML = `
        <div class="card w-full max-w-md p-6">
            <h2 class="text-xl text-ink-900" data-title>Are you sure?</h2>
            <p class="mt-2 text-sm text-ink-700" data-message></p>
            <div class="mt-6 flex justify-end gap-3">
                <button type="button" class="btn-ghost" data-cancel>Cancel</button>
                <button type="button" class="btn-danger" data-ok>Delete</button>
            </div>
        </div>`;
    document.body.append(modal);
    modal.querySelector('[data-cancel]').addEventListener('click', close);
    modal.addEventListener('click', (event) => event.target === modal && close());
    document.addEventListener('keydown', (event) => event.key === 'Escape' && close());
    modal.querySelector('[data-ok]').addEventListener('click', () => {
        const callback = pending;
        close();
        callback?.();
    });
}

function close() {
    modal.classList.add('hidden');
    modal.classList.remove('flex');
    pending = null;
}

export function confirmAction(message, onConfirm, { title = 'Are you sure?', okLabel = 'Delete', tone = 'danger' } = {}) {
    modal ?? build();
    modal.querySelector('[data-title]').textContent = title;
    modal.querySelector('[data-message]').textContent = message;
    const ok = modal.querySelector('[data-ok]');
    ok.textContent = okLabel;
    ok.className = tone === 'primary' ? 'btn-cta' : 'btn-danger';
    pending = onConfirm;
    modal.classList.remove('hidden');
    modal.classList.add('flex');
    modal.querySelector('[data-cancel]').focus();
}

export function initConfirm() {
    document.addEventListener('submit', (event) => {
        const form = event.target;

        if (!(form instanceof HTMLFormElement) || !form.dataset.confirm || form.dataset.confirmed) {
            return;
        }

        event.preventDefault();
        confirmAction(form.dataset.confirm, () => {
            form.dataset.confirmed = '1';
            form.requestSubmit();
        }, { title: form.dataset.confirmTitle, okLabel: form.dataset.confirmOk, tone: form.dataset.confirmTone });
    }, true);
}
