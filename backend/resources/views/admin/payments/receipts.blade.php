<x-admin.layout title="Receipts review">
    <x-admin.page-header title="Receipts review" :breadcrumbs="['Sales' => null, 'Receipts review' => null]" subtitle="Proofs of manual payments. Approving records the payment and confirms the booking." />
    <form id="receipt-filters" class="card mb-4 p-4" aria-label="Filters">
        <div class="grid grid-cols-1 gap-3 sm:grid-cols-3">
            <x-admin.select name="status" label="Status" :options="$statuses" selected="pending" :select2="false" span="" />
        </div>
        <div class="mt-3 flex justify-end"><button type="submit" class="btn-primary btn-sm">Apply</button></div>
    </form>
    <x-admin.datatable id="receipts-table" :url="route('admin.receipts.data')" filters="#receipt-filters" :order="[[5, 'asc']]" search-placeholder="Reference or name" empty="No proofs to review." :columns="[
        ['data' => 'booking', 'name' => 'booking', 'title' => 'Booking', 'orderable' => false],
        ['data' => 'method', 'name' => 'method', 'title' => 'Method', 'orderable' => false, 'searchable' => false],
        ['data' => 'purpose', 'name' => 'purpose', 'title' => 'For', 'searchable' => false],
        ['data' => 'amount', 'name' => 'amount', 'title' => 'Declared', 'searchable' => false, 'className' => 'text-right tabular-nums'],
        ['data' => 'expected', 'name' => 'expected', 'title' => 'Deposit (EUR)', 'orderable' => false, 'searchable' => false, 'className' => 'text-right tabular-nums'],
        ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Uploaded', 'searchable' => false],
        ['data' => 'status', 'name' => 'status', 'title' => 'Status', 'searchable' => false],
        ['data' => 'actions', 'name' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]" />
</x-admin.layout>
