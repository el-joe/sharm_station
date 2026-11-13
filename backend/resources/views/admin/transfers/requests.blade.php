<x-admin.layout title="Transfer requests">
    <x-admin.page-header title="Transfer requests" :breadcrumbs="['Transfers' => null, 'Requests' => null]" subtitle="Paid standalone transfers. Rides are arranged outside the system; record the driver and car here." />
    <form id="transfer-filters" class="card mb-4 p-4" aria-label="Filters">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-admin.select name="status" label="Status" :options="$statuses" placeholder="Any status" data-s2-clear span="" />
        </div>
        <div class="mt-3 flex justify-end gap-2">
            <button type="reset" class="btn-ghost btn-sm">Clear</button>
            <button type="submit" class="btn-primary btn-sm">Apply</button>
        </div>
    </form>
    <x-admin.datatable id="transfer-requests-table" :url="route('admin.transfers.requests.data')" filters="#transfer-filters" :order="[[1, 'asc']]" search-placeholder="Reference or name" :columns="[
        ['data' => 'reference', 'name' => 'reference', 'title' => 'Booking', 'orderable' => false],
        ['data' => 'pickup_at', 'name' => 'pickup_at', 'title' => 'Pickup', 'searchable' => false],
        ['data' => 'route', 'name' => 'route', 'title' => 'Route', 'orderable' => false, 'searchable' => false],
        ['data' => 'vehicle', 'name' => 'vehicle', 'title' => 'Vehicle', 'orderable' => false, 'searchable' => false],
        ['data' => 'price', 'name' => 'price', 'title' => 'Price', 'searchable' => false, 'className' => 'text-right tabular-nums'],
        ['data' => 'status', 'name' => 'status', 'title' => 'Status', 'searchable' => false],
        ['data' => 'actions', 'name' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]" />
</x-admin.layout>
