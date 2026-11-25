import $ from 'jquery';
import select2 from 'select2';

window.$ = window.jQuery = $;
select2(window, $);

/**
 * <select data-s2 [data-s2-tags] [data-placeholder] [data-s2-url="remote search, ?q= → {results: [{id, text}]}"]> → Select2.
 */
export function initSelect2(root = document) {
    root.querySelectorAll('select[data-s2]').forEach((select) => {
        if (select.dataset.s2Ready) {
            return;
        }

        select.dataset.s2Ready = '1';
        $(select)
            .select2({
                width: '100%',
                tags: select.hasAttribute('data-s2-tags'),
                allowClear: !select.multiple && select.hasAttribute('data-s2-clear'),
                placeholder: select.dataset.placeholder ?? '',
                dropdownParent: select.closest('dialog') ? $(select.closest('dialog')) : $(document.body),
                ...(select.dataset.s2Url ? {
                    minimumInputLength: 2,
                    ajax: {
                        url: select.dataset.s2Url,
                        delay: 250,
                        dataType: 'json',
                        data: (params) => ({ q: params.term }),
                        processResults: (data) => ({ results: data.results ?? [] }),
                    },
                } : {}),
            })
            .on('change', () => select.dispatchEvent(new Event('select2:change', { bubbles: true })))
            .on('select2:select', (event) => select.dispatchEvent(new CustomEvent('select2:item', { bubbles: true, detail: event.params?.data ?? null })));
    });
}

export { $ };
