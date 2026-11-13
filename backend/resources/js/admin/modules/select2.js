import $ from 'jquery';
import select2 from 'select2';

window.$ = window.jQuery = $;
select2(window, $);

/**
 * <select data-s2 [data-s2-tags] [data-placeholder]> → Select2.
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
            })
            .on('change', () => select.dispatchEvent(new Event('select2:change', { bubbles: true })));
    });
}

export { $ };
