/**
 * Admin panel entry: vanilla ES modules, jQuery only for Select2.
 * Each module is activated by data attributes in the Blade markup. Heavy libraries (DataTables, Select2 + jQuery,
 * flatpickr, Chart.js, SortableJS, Google Maps picker) are split into chunks loaded only when the page uses them.
 */
import { initShell } from './modules/shell.js';
import { initToasts } from './modules/toast.js';
import { initConfirm } from './modules/confirm.js';
import { initTabs } from './modules/tabs.js';
import { initRepeaters } from './modules/repeater.js';
import { initUnitOptions } from './modules/unit-options.js';
import { initSeasonEditor } from './modules/season-editor.js';
import { initItinerary } from './modules/itinerary.js';
import { initDependentFields } from './modules/dependent-fields.js';
import { initRoomCalendar } from './modules/room-calendar.js';
import { initAjaxToggles, initAjaxActions } from './modules/ajax-actions.js';
import { initClipboard, initDialogs, initCharCounters } from './modules/clipboard.js';
import { initRichEditors } from './modules/rich-editor.js';
import { initOccupancyCalendar } from './modules/occupancy-calendar.js';
import { initTemplatePreview } from './modules/template-preview.js';

/**
 * Loads a module chunk when the root contains (or is) an element matching the selector, then runs its init.
 */
function lazy(root, selector, loader, init) {
    if ((root.matches?.(selector)) || root.querySelector(selector)) {
        return loader().then(init).catch((error) => console.error(`Admin module for ${selector} failed to load`, error));
    }

    return Promise.resolve();
}

function boot(root = document) {
    lazy(root, 'select[data-s2]', () => import('./modules/select2.js'), ({ initSelect2 }) => initSelect2(root));
    lazy(root, 'input[data-datepicker]', () => import('./modules/datepicker.js'), ({ initDatePickers }) => initDatePickers(root));
    initDependentFields(root);
}

document.addEventListener('DOMContentLoaded', () => {
    initShell();
    initToasts();
    initConfirm();
    initTabs();
    initRepeaters(boot);
    initUnitOptions(boot);
    initSeasonEditor(boot);
    initItinerary(boot);
    boot();
    lazy(document, 'table[data-datatable]', () => import('./modules/datatables.js'), ({ initDataTables }) => initDataTables());
    lazy(document, 'canvas[data-chart]', () => import('./modules/charts.js'), ({ initCharts }) => initCharts());
    lazy(document, 'canvas[data-report-chart]', () => import('./modules/report-charts.js'), ({ initReportCharts }) => initReportCharts());
    lazy(document, '[data-sortable-list]', () => import('./modules/sortable-list.js'), ({ initSortableLists }) => initSortableLists());
    lazy(document, '[data-location-picker]', () => import('./modules/location-picker.js'), ({ initLocationPickers }) => initLocationPickers());
    lazy(document, '[data-occupancy-move]', () => import('./modules/calendar-drag.js'), ({ initCalendarDrag }) => initCalendarDrag());
    lazy(document, '[data-booking-editor]', () => import('./modules/booking-editor.js'), ({ initBookingEditor }) => initBookingEditor());
    initRoomCalendar();
    initAjaxToggles();
    initAjaxActions();
    initClipboard();
    initDialogs();
    initCharCounters();
    initRichEditors();
    initOccupancyCalendar();
    initTemplatePreview();
});
