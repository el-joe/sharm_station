<x-admin.layout title="Payments">
    <x-admin.page-header title="Payments" :breadcrumbs="['Sales' => null, 'Payments' => null]" subtitle="Every payment attempt and recorded payment. Amounts in EUR, with the customer currency below." />
    <form id="payment-filters" class="card mb-4 p-4" aria-label="Filters">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-admin.select name="status" label="Status" :options="$statuses" placeholder="Any status" data-s2-clear span="" />
        </div>
        <div class="mt-3 flex justify-end gap-2"><button type="reset" class="btn-ghost btn-sm">Clear</button><button type="submit" class="btn-primary btn-sm">Apply</button></div>
    </form>
    <x-admin.datatable id="payments-table" :url="route('admin.payments.data')" filters="#payment-filters" :order="[[5, 'desc']]" search-placeholder="Booking reference" :columns="[
        ['data' => 'booking', 'name' => 'booking', 'title' => 'Booking', 'orderable' => false],
        ['data' => 'method', 'name' => 'method', 'title' => 'Method', 'orderable' => false, 'searchable' => false],
        ['data' => 'type', 'name' => 'type', 'title' => 'Type', 'searchable' => false],
        ['data' => 'amount', 'name' => 'amount', 'title' => 'Amount', 'searchable' => false, 'className' => 'text-right tabular-nums'],
        ['data' => 'status', 'name' => 'status', 'title' => 'Status', 'searchable' => false],
        ['data' => 'paid_at', 'name' => 'paid_at', 'title' => 'Paid at', 'searchable' => false],
        ['data' => 'recorded_by', 'name' => 'recorded_by', 'title' => 'By', 'orderable' => false, 'searchable' => false],
    ]" />
</x-admin.layout>
