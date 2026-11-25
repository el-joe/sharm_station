import { request } from './http.js';
import { toast } from './toast.js';

/**
 * Notification template editor: re-renders the branded preview (iframe srcdoc) from the unsaved form.
 * <form data-template-preview="url" data-preview-frame="#iframe" data-preview-subject="#subject">
 */
export function initTemplatePreview(root = document) {
    root.querySelectorAll('form[data-template-preview]').forEach((form) => {
        const frame = document.querySelector(form.dataset.previewFrame);
        const subject = document.querySelector(form.dataset.previewSubject);
        let timer = null;

        const refresh = async () => {
            const body = new FormData(form);
            body.delete('_method');

            try {
                const data = await request(form.dataset.templatePreview, { method: 'POST', body });
                frame.srcdoc = data.html;
                subject.textContent = data.subject;
            } catch (error) {
                toast(Object.values(error.data?.errors ?? {})[0]?.[0] ?? error.message, 'error');
            }
        };

        form.querySelector('[data-preview-refresh]')?.addEventListener('click', refresh);
        form.addEventListener('input', () => {
            clearTimeout(timer);
            timer = setTimeout(refresh, 600);
        });
    });
}
