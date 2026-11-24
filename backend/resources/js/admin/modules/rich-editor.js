/**
 * Small HTML editor: <div data-rich-editor> with toolbar buttons [data-cmd], a <textarea data-rich-source>
 * and a live <div data-rich-preview>. Buttons wrap the selected text in the tag; the server sanitizes the HTML.
 */
const BLOCKS = { h2: 'h2', h3: 'h3', p: 'p', blockquote: 'blockquote' };

function wrap(textarea, before, after, placeholder) {
    const { selectionStart: start, selectionEnd: end, value } = textarea;
    const selected = value.slice(start, end) || placeholder;
    textarea.setRangeText(`${before}${selected}${after}`, start, end, 'end');
    textarea.focus();
    textarea.selectionStart = start + before.length;
    textarea.selectionEnd = start + before.length + selected.length;
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
}

function list(textarea, tag) {
    const { selectionStart: start, selectionEnd: end, value } = textarea;
    const lines = (value.slice(start, end) || 'Item').split(/\n+/).filter((line) => line.trim() !== '');
    const html = `<${tag}>\n${lines.map((line) => `  <li>${line.trim()}</li>`).join('\n')}\n</${tag}>`;
    textarea.setRangeText(html, start, end, 'end');
    textarea.focus();
    textarea.dispatchEvent(new Event('input', { bubbles: true }));
}

/** Preview only: drops scripts, frames and event handlers (the saved HTML is sanitized on the server). */
function safePreview(html) {
    const doc = new DOMParser().parseFromString(`<div>${html}</div>`, 'text/html');
    doc.querySelectorAll('script, style, iframe, object, embed, form').forEach((node) => node.remove());
    doc.querySelectorAll('*').forEach((node) => {
        [...node.attributes].forEach((attribute) => {
            if (attribute.name.startsWith('on') || attribute.name === 'style' || /^\s*javascript:/i.test(attribute.value)) {
                node.removeAttribute(attribute.name);
            }
        });
    });

    return doc.body.firstElementChild.innerHTML;
}

export function initRichEditors(root = document) {
    root.querySelectorAll('[data-rich-editor]').forEach((editor) => {
        const textarea = editor.querySelector('[data-rich-source]');
        const preview = editor.querySelector('[data-rich-preview]');
        const render = () => {
            preview.innerHTML = safePreview(textarea.value) || '<p class="text-ink-500">Nothing to preview yet.</p>';
        };

        editor.querySelectorAll('[data-cmd]').forEach((button) => {
            button.addEventListener('click', () => {
                const command = button.dataset.cmd;

                if (BLOCKS[command]) {
                    wrap(textarea, `<${command}>`, `</${command}>`, 'Text');
                } else if (command === 'strong' || command === 'em') {
                    wrap(textarea, `<${command}>`, `</${command}>`, 'text');
                } else if (command === 'ul' || command === 'ol') {
                    list(textarea, command);
                } else if (command === 'a') {
                    const url = window.prompt('Link address (https://…, /page or mailto:)', 'https://');

                    if (url) {
                        wrap(textarea, `<a href="${url.replace(/"/g, '&quot;')}">`, '</a>', 'link text');
                    }
                }
            });
        });

        textarea.addEventListener('input', render);
        render();
    });
}
