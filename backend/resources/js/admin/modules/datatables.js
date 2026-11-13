import DataTable from 'datatables.net-dt';

/**
 * Server-side Yajra DataTables.
 *
 * <table data-datatable data-url="..." data-columns='[{"data":"reference","title":"Reference"}]'
 *        data-order='[[0,"desc"]]' data-filters="#booking-filters" data-export="#export-link">
 *
 * Filter form fields are sent with every request; the export link gets the same query string.
 */
function filterParams(form) {
    if (!form) {
        return {};
    }

    const params = {};

    new FormData(form).forEach((value, key) => {
        if (value === '') {
            return;
        }

        if (key.endsWith('[]')) {
            (params[key.slice(0, -2)] ??= []).push(value);
        } else {
            params[key] = value;
        }
    });

    return params;
}

function toQuery(params) {
    const query = new URLSearchParams();

    Object.entries(params).forEach(([key, value]) => {
        (Array.isArray(value) ? value : [value]).forEach((item) => query.append(Array.isArray(value) ? `${key}[]` : key, item));
    });

    return query.toString();
}

export function initDataTables() {
    document.querySelectorAll('table[data-datatable]').forEach((table) => {
        const columns = JSON.parse(table.dataset.columns);
        const filters = table.dataset.filters ? document.querySelector(table.dataset.filters) : null;
        const exportLink = table.dataset.export ? document.querySelector(table.dataset.export) : null;
        table.classList.add('dt-stack', 'display');

        const dataTable = new DataTable(table, {
            serverSide: true,
            processing: true,
            searching: table.dataset.search !== 'false',
            ajax: {
                url: table.dataset.url,
                data: (data) => Object.assign(data, filterParams(filters)),
            },
            columns: columns.map((column) => ({
                orderable: column.orderable ?? true,
                searchable: column.searchable ?? true,
                ...column,
            })),
            order: table.dataset.order ? JSON.parse(table.dataset.order) : [],
            pageLength: Number(table.dataset.pageLength ?? 10),
            lengthMenu: [10, 25, 50, 100],
            autoWidth: false,
            language: {
                search: '',
                searchPlaceholder: table.dataset.searchPlaceholder ?? 'Search…',
                emptyTable: table.dataset.empty ?? 'Nothing here yet.',
                info: 'Showing _START_–_END_ of _TOTAL_',
                infoEmpty: 'Showing 0 of 0',
                lengthMenu: 'Rows per page _MENU_',
            },
            createdRow: (row) => {
                [...row.children].forEach((cell, index) => cell.setAttribute('data-label', columns[index]?.title ?? ''));
            },
        });

        const updateExport = () => {
            if (exportLink) {
                const params = filterParams(filters);
                const search = dataTable.search();

                if (search) {
                    params.search = search;
                }

                exportLink.href = `${exportLink.dataset.base}?${toQuery(params)}`;
            }
        };

        filters?.addEventListener('change', () => dataTable.ajax.reload());
        filters?.addEventListener('select2:change', () => dataTable.ajax.reload());
        filters?.addEventListener('submit', (event) => {
            event.preventDefault();
            dataTable.ajax.reload();
        });
        filters?.addEventListener('reset', () => setTimeout(() => {
            filters.querySelectorAll('select[data-s2]').forEach((select) => window.jQuery?.(select).val(null).trigger('change.select2'));
            filters.querySelectorAll('input[data-datepicker]').forEach((input) => input._flatpickr?.clear());
            dataTable.ajax.reload();
        }));
        dataTable.on('draw', updateExport);
        table.dataTableInstance = dataTable;
    });
}
