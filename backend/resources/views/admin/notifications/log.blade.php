<x-admin.layout title="Sending log">
    <x-admin.page-header title="Notifications" :breadcrumbs="['Settings' => null, 'Notifications' => route('admin.notifications.index'), 'Sending log' => null]" subtitle="Every email queued by the platform, with its delivery status." />
    @include('admin.notifications._tabs', ['active' => 'log'])

    <form id="notification-filters" class="card mb-4 p-4" aria-label="Filters">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="f-search" class="form-label">Search</label>
                <input id="f-search" name="search" type="search" placeholder="Recipient or subject" class="form-control">
            </div>
            <x-admin.select name="event" label="Event" :options="$events" placeholder="Any event" data-s2-clear span="" />
            <x-admin.select name="status" label="Status" :options="$statuses" placeholder="Any status" :select2="false" span="" />
            <div class="grid grid-cols-2 gap-2">
                <x-admin.input name="date_from" label="From" data-datepicker placeholder="dd/mm/yyyy" span="" />
                <x-admin.input name="date_to" label="To" data-datepicker placeholder="dd/mm/yyyy" span="" />
            </div>
        </div>
        <div class="mt-3 flex justify-end gap-2">
            <button type="reset" class="btn-ghost btn-sm">Clear filters</button>
            <button type="submit" class="btn-primary btn-sm">Apply</button>
        </div>
    </form>

    <x-admin.datatable id="notification-log-table" :url="route('admin.notifications.log.data')" filters="#notification-filters" :search="false" :order="[[0, 'desc']]" empty="No emails sent yet." :columns="[
        ['data' => 'created_at', 'name' => 'created_at', 'title' => 'When', 'searchable' => false],
        ['data' => 'recipient', 'name' => 'recipient', 'title' => 'Recipient and subject', 'searchable' => false],
        ['data' => 'event', 'name' => 'event', 'title' => 'Event', 'searchable' => false],
        ['data' => 'status', 'name' => 'status', 'title' => 'Status', 'searchable' => false],
        ['data' => 'related', 'name' => 'related', 'title' => 'Record', 'searchable' => false, 'orderable' => false],
        ['data' => 'actions', 'name' => 'actions', 'title' => '', 'searchable' => false, 'orderable' => false],
    ]" />
</x-admin.layout>
