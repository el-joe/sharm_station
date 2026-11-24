@php
    use App\Enums\CouponDiscountType;
    use App\Enums\ServiceType;
    $title = $coupon->exists ? $coupon->code : 'New coupon';
@endphp
<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :breadcrumbs="['Sales' => null, 'Coupons' => route('admin.coupons.index'), $title => null]"
                         :subtitle="$coupon->exists ? 'Used '.$usage.' '.str('time')->plural($usage).($coupon->usage_limit ? ' of '.$coupon->usage_limit : '') : null">
        <x-slot:actions><button type="submit" form="coupon-form" class="btn-cta">Save</button></x-slot:actions>
    </x-admin.page-header>
    <form id="coupon-form" method="POST" action="{{ $coupon->exists ? route('admin.coupons.update', $coupon) : route('admin.coupons.store') }}" novalidate class="space-y-4">
        @csrf
        @if ($coupon->exists) @method('PUT') @endif
        <x-admin.form-section title="Code">
            <x-admin.input name="code" label="Code" :value="$coupon->code" required maxlength="40" autocomplete="off" class="uppercase" hint="Letters, numbers, dashes. Saved in capitals." span="md:col-span-2" />
            <x-admin.input name="description" label="Description" :value="$coupon->description" maxlength="255" hint="Internal note, e.g. Winter newsletter 2026" span="md:col-span-4" />
            <x-admin.select name="discount_type" label="Discount type" :options="CouponDiscountType::options()" :selected="$coupon->discount_type" :select2="false" span="md:col-span-2" />
            <x-admin.input name="value" label="Value" type="number" step="0.01" min="0" :value="$coupon->value !== null ? (float) $coupon->value : null" required hint="Percent (1–100) or EUR amount" span="md:col-span-2" />
            <x-admin.money name="minimum_total" label="Minimum total" :value="$coupon->minimum_total" hint="Leave empty for no minimum" span="md:col-span-2" />
        </x-admin.form-section>
        <x-admin.form-section title="Validity and limits">
            <x-admin.input name="valid_from" label="Valid from" data-datepicker :value="$coupon->valid_from?->toDateString()" placeholder="dd/mm/yyyy" span="md:col-span-3" />
            <x-admin.input name="valid_to" label="Valid until" data-datepicker :value="$coupon->valid_to?->toDateString()" placeholder="dd/mm/yyyy" span="md:col-span-3" />
            <x-admin.input name="usage_limit" label="Total uses" type="number" min="1" :value="$coupon->usage_limit" hint="Empty = unlimited" span="md:col-span-3" />
            <x-admin.input name="usage_limit_per_customer" label="Uses per customer" type="number" min="1" :value="$coupon->usage_limit_per_customer" hint="Counted by account or email" span="md:col-span-3" />
            <x-admin.select name="services[]" label="Applies to" :options="ServiceType::options()" :selected="$coupon->services ?? []" multiple placeholder="All services" span="md:col-span-6" />
            <x-admin.switch name="partners_allowed" label="Partners allowed" description="Also valid on partner bookings, for partners with coupon permission" :checked="$coupon->partners_allowed" />
            <x-admin.switch name="is_active" label="Active" :checked="$coupon->is_active" />
        </x-admin.form-section>
    </form>
</x-admin.layout>
