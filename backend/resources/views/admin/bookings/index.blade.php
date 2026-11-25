@php
    use App\Enums\BookingStatus;
    use App\Enums\BookingType;
    use App\Enums\PaymentStatus;
    use App\Enums\ServiceType;
@endphp
<x-admin.layout title="Bookings">
    <x-admin.page-header title="Bookings" :breadcrumbs="['Sales' => null, 'Bookings' => null]" subtitle="All services. Amounts in EUR.">
        <x-slot:actions>
            <a href="{{ route('admin.bookings.create') }}" class="btn-cta">New booking</a>
            <a id="bookings-export" href="{{ route('admin.bookings.export', array_filter($initial)) }}" data-base="{{ route('admin.bookings.export') }}" class="btn-primary">
                <x-admin.icon name="download" size="18" /> Export CSV
            </a>
        </x-slot:actions>
    </x-admin.page-header>

    <form id="booking-filters" class="card mb-4 p-4" aria-label="Filters">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-4">
            <div class="sm:col-span-2">
                <label for="f-search" class="form-label">Search</label>
                <div class="relative">
                    <x-admin.icon name="search" size="18" class="pointer-events-none absolute top-1/2 left-3 -translate-y-1/2 text-ink-500" />
                    <input id="f-search" name="search" type="search" value="{{ $initial['search'] ?? '' }}" placeholder="Reference, name, email or phone" class="form-control pl-10">
                </div>
            </div>
            <x-admin.select name="service_type" label="Service" :options="ServiceType::options()" :selected="$initial['service_type'] ?? null" placeholder="All services" data-s2-clear span="" />
            <x-admin.select name="booking_type" label="Booking type" :options="BookingType::options()" placeholder="Private and group" data-s2-clear span="" />
            <x-admin.select name="status" label="Status" :options="BookingStatus::options()" :selected="$initial['status'] ?? null" placeholder="Any status" data-s2-clear span="" />
            <x-admin.select name="payment_status" label="Payment" :options="PaymentStatus::options()" :selected="$initial['payment_status'] ?? null" placeholder="Any payment status" data-s2-clear span="" />
            <x-admin.select name="cancellation" label="Cancellation" :options="['pending' => 'Pending requests', 'any' => 'All requests']" :selected="$initial['cancellation'] ?? null" placeholder="Any" data-s2-clear span="" />
            <x-admin.select name="partner_id" label="Partner" :options="$partners" :selected="$initial['partner_id'] ?? null" placeholder="All partners" data-s2-clear span="" />
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

    <x-admin.datatable id="bookings-table" :url="route('admin.bookings.data')" filters="#booking-filters" export="#bookings-export" :search="false"
                       :order="[[0, 'desc']]" empty="No bookings match these filters." :columns="[
        ['data' => 'reference', 'name' => 'reference', 'title' => 'Reference', 'searchable' => false],
        ['data' => 'service_type', 'name' => 'service_type', 'title' => 'Service', 'searchable' => false],
        ['data' => 'lead_name', 'name' => 'lead_name', 'title' => 'Booker', 'searchable' => false],
        ['data' => 'service_date', 'name' => 'service_date', 'title' => 'Date', 'searchable' => false],
        ['data' => 'total', 'name' => 'total', 'title' => 'Total', 'searchable' => false, 'className' => 'text-right tabular-nums'],
        ['data' => 'paid', 'name' => 'paid', 'title' => 'Paid', 'searchable' => false, 'orderable' => false, 'className' => 'text-right tabular-nums'],
        ['data' => 'balance', 'name' => 'balance', 'title' => 'Balance', 'searchable' => false, 'orderable' => false, 'className' => 'text-right tabular-nums'],
        ['data' => 'status', 'name' => 'status', 'title' => 'Status', 'searchable' => false],
        ['data' => 'payment_status', 'name' => 'payment_status', 'title' => 'Payment', 'searchable' => false],
        ['data' => 'actions', 'name' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]" />
</x-admin.layout>
