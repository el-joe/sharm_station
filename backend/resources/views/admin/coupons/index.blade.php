<x-admin.layout title="Coupons">
    <x-admin.page-header title="Coupons" :breadcrumbs="['Sales' => null, 'Coupons' => null]" subtitle="Discount codes for the website checkout. Partner bookings only accept codes that allow partners.">
        <x-slot:actions><a href="{{ route('admin.coupons.create') }}" class="btn-cta"><x-admin.icon name="plus" size="18" /> New coupon</a></x-slot:actions>
    </x-admin.page-header>

    <form id="coupon-filters" class="card mb-4 flex flex-wrap items-end gap-3 p-4" aria-label="Filters">
        <x-admin.select name="active" label="Status" :options="['1' => 'Active', '0' => 'Inactive']" placeholder="Any status" data-s2-clear span="w-full sm:w-56" />
    </form>

    <x-admin.datatable id="coupons-table" :url="route('admin.coupons.data')" filters="#coupon-filters" :order="[[0, 'asc']]" search-placeholder="Search code or description"
                       empty="No coupons yet." :columns="[
        ['data' => 'code', 'name' => 'code', 'title' => 'Code'],
        ['data' => 'value', 'name' => 'value', 'title' => 'Discount', 'searchable' => false, 'className' => 'tabular-nums'],
        ['data' => 'validity', 'name' => 'validity', 'title' => 'Valid', 'searchable' => false, 'orderable' => false],
        ['data' => 'services_list', 'name' => 'services_list', 'title' => 'Services', 'searchable' => false, 'orderable' => false],
        ['data' => 'redemptions_count', 'name' => 'redemptions_count', 'title' => 'Used', 'searchable' => false],
        ['data' => 'redemptions_sum_discount_amount', 'name' => 'redemptions_sum_discount_amount', 'title' => 'Discount given', 'searchable' => false, 'className' => 'text-right tabular-nums'],
        ['data' => 'is_active', 'name' => 'is_active', 'title' => 'Status', 'searchable' => false],
        ['data' => 'actions', 'name' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]" />
</x-admin.layout>
