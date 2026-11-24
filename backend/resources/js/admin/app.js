/**
 * Admin panel entry: vanilla ES modules, jQuery only for Select2.
 * Each module is activated by data attributes in the Blade markup.
 */
import { initShell } from './modules/shell.js';
import { initToasts } from './modules/toast.js';
import { initConfirm } from './modules/confirm.js';
import { initSelect2 } from './modules/select2.js';
import { initDatePickers } from './modules/datepicker.js';
import { initDataTables } from './modules/datatables.js';
import { initTabs } from './modules/tabs.js';
import { initRepeaters } from './modules/repeater.js';
import { initUnitOptions } from './modules/unit-options.js';
import { initSeasonEditor } from './modules/season-editor.js';
import { initItinerary } from './modules/itinerary.js';
import { initDependentFields } from './modules/dependent-fields.js';
import { initCharts } from './modules/charts.js';
import { initRoomCalendar } from './modules/room-calendar.js';
import { initAjaxToggles, initAjaxActions } from './modules/ajax-actions.js';
import { initClipboard, initDialogs, initCharCounters } from './modules/clipboard.js';
import { initRichEditors } from './modules/rich-editor.js';
import { initSortableLists } from './modules/sortable-list.js';
import { initOccupancyCalendar } from './modules/occupancy-calendar.js';
import { initReportCharts } from './modules/report-charts.js';
import { initLocationPickers } from './modules/location-picker.js';

function boot(root = document) {
    initSelect2(root);
    initDatePickers(root);
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
    initDataTables();
    initCharts();
    initRoomCalendar();
    initAjaxToggles();
    initAjaxActions();
    initClipboard();
    initDialogs();
    initCharCounters();
    initRichEditors();
    initSortableLists();
    initOccupancyCalendar();
    initReportCharts();
    initLocationPickers();
});
