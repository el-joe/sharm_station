@php use App\Enums\PartnerStatus; @endphp
<x-admin.layout title="Partners">
    <x-admin.page-header title="Partners" :breadcrumbs="['Sales' => null, 'Partners' => null]" subtitle="Travel agencies and resellers: applications, approval and suspension.">
        <x-slot:actions><a href="{{ route('admin.partners.commissions.edit') }}" class="btn-primary">Commission matrix</a></x-slot:actions>
    </x-admin.page-header>

    <nav aria-label="Partner status" class="mb-4 flex flex-wrap gap-2">
        @foreach (['' => 'All'] + PartnerStatus::options() as $value => $label)
            @php($active = ($status?->value ?? '') === $value)
            <a href="{{ route('admin.partners.index', array_filter(['status' => $value])) }}" @if ($active) aria-current="page" @endif
               class="rounded-pill border px-4 py-1.5 text-sm font-medium {{ $active ? 'border-primary-700 bg-primary-700 text-white' : 'border-ink-300 bg-white text-ink-700 hover:bg-ink-100' }}">{{ $label }}</a>
        @endforeach
    </nav>

    <x-admin.datatable id="partners-table" :url="route('admin.partners.data', array_filter(['status' => $status?->value]))" :order="[[5, 'desc']]" search-placeholder="Search company or contact" :columns="[
        ['data' => 'company', 'name' => 'company', 'title' => 'Company'],
        ['data' => 'contact', 'name' => 'contact', 'title' => 'Contact', 'orderable' => false, 'searchable' => false],
        ['data' => 'status', 'name' => 'status', 'title' => 'Status', 'searchable' => false],
        ['data' => 'default_commission', 'name' => 'default_commission', 'title' => 'Default commission', 'searchable' => false],
        ['data' => 'sales_total', 'name' => 'sales_total', 'title' => 'Sales', 'searchable' => false, 'className' => 'text-right tabular-nums'],
        ['data' => 'created_at', 'name' => 'created_at', 'title' => 'Applied', 'searchable' => false],
        ['data' => 'actions', 'name' => 'actions', 'title' => '', 'orderable' => false, 'searchable' => false, 'className' => 'text-right'],
    ]" />
</x-admin.layout>
