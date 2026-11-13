{{-- EUR amount; partner fields get the commission preview placeholder (SRS 8.5). --}}
@props(['name', 'label' => null, 'value' => null, 'hint' => null, 'partnerFor' => null, 'retailFor' => null, 'commission' => null, 'span' => 'md:col-span-3', 'id' => null])
@php
    $extra = [];
    if ($retailFor) {
        $extra['data-retail-for'] = $retailFor;
    }
    if ($partnerFor) {
        $extra['data-partner-for'] = $partnerFor;
        $extra['data-commission'] = $commission ?? app(\App\Services\SettingsService::class)->previewCommission();
    }
@endphp
<x-admin.input :name="$name" :label="$label" type="number" step="0.01" min="0" inputmode="decimal" prefix="€"
               :value="is_null($value) ? null : number_format((float) $value, 2, '.', '')" :hint="$hint" :span="$span" :id="$id"
               {{ $attributes->merge($extra) }} />
