<x-admin.layout title="Customers">
    <x-admin.page-header title="Customers" :breadcrumbs="['Sales' => null, 'Customers' => null]" subtitle="Website accounts and their bookings. Totals in EUR, cancelled bookings excluded." />

    <form id="customer-filters" class="card mb-4 p-4" aria-label="Filters">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div>
                <label for="f-search" class="form-label">Search</label>
                <input id="f-search" name="search" type="search" placeholder="Name, email or phone" class="form-control">
            </div>
            <x-admin.select name="nationality_code" label="Nationality" :options="$countries" placeholder="Any nationality" data-s2-clear span="" />
            <x-admin.select name="active" label="Status" :options="['1' => 'Active', '0' => 'Deactivated']" placeholder="Any status" data-s2-clear span="" />
            <x-admin.select name="with_bookings" label="Bookings" :options="['1' => 'With bookings', '0' => 'Without bookings']" placeholder="All customers" data-s2-clear span="" />
        </div>
        <div class="mt-3 flex justify-end gap-2">
            <button type="reset" class="btn-ghost btn-sm">Clear filters</button>
            <button type="submit" class="btn-primary btn-sm">Apply</button>
        </div>
    </form>

    <x-admin.datatable id="customers-table" :url="route('admin.customers.data')" filters="#customer-filters" :search="false" :order="[[6, 'desc']]" empty="No customers match these filters." :columns="[
        ['data' => 'name', 'name' => 'name', 'title' => 'Customer', 'searchable' => false],
        ['data' => 'phone', 'name' => 'phone', 'title' => 'Phone', 'searchable' => false, 'orderable' => false],
        ['data' => 'nationality_code', 'name' => 'nationality_code', 'title' => 'Nationality', 'searchable' => false],
        ['data' => 'bookings_count', 'name' => 'bookings_count', 'title' => 'Bookings', 'searchable' => false, 'className' => 'text-right tabular-nums'],
        ['data' => 'total_spent', 'name' => 'total_spent', 'title' => 'Total spent', 'searchable' => false, 'className' => 'text-right tabular-nums'],
        ['data' => 'is_active', 'name' => 'is_active', 'title' => 'Status', 'searchable' => false],
        ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Joined', 'searchable' => false],
        ['data' => 'actions', 'name' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]" />
</x-admin.layout>
