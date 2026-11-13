<x-admin.layout title="Payment methods">
    <x-admin.page-header title="Payment methods" :breadcrumbs="['Settings' => null, 'Payment methods' => null]" subtitle="Online gateways and manual transfer methods, shown only for the currencies they accept.">
        @if ($canPrice)
            <x-slot:actions><a href="{{ route('admin.payment-methods.create') }}" class="btn-cta"><x-admin.icon name="plus" size="18" /> New method</a></x-slot:actions>
        @endif
    </x-admin.page-header>
    <x-admin.datatable id="payment-methods-table" :url="route('admin.payment-methods.data')" :order="[[5, 'asc']]" :columns="[
        ['data' => 'name', 'name' => 'name', 'title' => 'Method'],
        ['data' => 'type', 'name' => 'type', 'title' => 'Type', 'searchable' => false],
        ['data' => 'currencies', 'name' => 'currencies', 'title' => 'Currencies', 'orderable' => false, 'searchable' => false],
        ['data' => 'proof_required', 'name' => 'proof_required', 'title' => 'Proof', 'searchable' => false],
        ['data' => 'is_enabled', 'name' => 'is_enabled', 'title' => 'Status', 'searchable' => false],
        ['data' => 'sort', 'name' => 'sort', 'title' => 'Order', 'searchable' => false],
        ['data' => 'actions', 'name' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]" />
</x-admin.layout>
