{{-- Optional room fields (SRS 7.1). @param RoomOption $option, Trip|Package $owner --}}
@php
    use App\Enums\RoomOptionUnitType;
    use App\Enums\RoomPricePeriod;
    use App\Enums\RoomPricingMode;
    $stageOptions = $owner instanceof \App\Models\Package
        ? $owner->stages->mapWithKeys(fn ($stage) => [$stage->position => 'Stage '.$stage->position.($stage->destination ? ' · '.$stage->destination->name : '').' ('.$stage->nights.' nights)'])->all()
        : null;
@endphp
<x-admin.form-section title="Room">
    <x-admin.translatable name="name" label="Name" :value="$option->getTranslations('name')" required-locale="en" span="md:col-span-4" />
    <x-admin.select name="unit_type" label="Unit type" :options="RoomOptionUnitType::options()" :selected="$option->unit_type" :select2="false" span="md:col-span-1" />
    <x-admin.select name="stars" label="Stars" :options="[1 => '1 ★', 2 => '2 ★', 3 => '3 ★', 4 => '4 ★', 5 => '5 ★']" :selected="$option->stars" placeholder="None" :select2="false" span="md:col-span-1" />
    @if ($stageOptions !== null)
        <x-admin.select name="stage_position" label="Package stage" :options="$stageOptions" :selected="$option->stage_position" placeholder="Choose a stage" :select2="false" span="md:col-span-3" />
    @endif
</x-admin.form-section>
<x-admin.form-section title="Price" description="Customer price in EUR for the base nationality group; seasons and other groups are set in the price matrix after saving. Partners get the product's commission.">
    <x-admin.select name="pricing_mode" label="Priced" :options="RoomPricingMode::options()" :selected="$option->pricing_mode" :select2="false" span="md:col-span-2" />
    <x-admin.select name="price_period" label="Period" :options="RoomPricePeriod::options()" :selected="$option->price_period" :select2="false" span="md:col-span-2" />
    <x-admin.money name="price" label="Price" :value="$option->price" span="md:col-span-2" />
</x-admin.form-section>
<x-admin.form-section title="Capacity">
    <x-admin.input name="max_adults" label="Max adults" type="number" min="1" :value="$option->max_adults" span="md:col-span-1" />
    <x-admin.input name="max_children" label="Max children" type="number" min="0" :value="$option->max_children" span="md:col-span-1" />
    <x-admin.input name="max_total" label="Max total" type="number" min="1" :value="$option->max_total" span="md:col-span-1" />
    <x-admin.input name="max_quantity" label="Max per booking" type="number" min="1" :value="$option->max_quantity" span="md:col-span-1" />
    <x-admin.input name="sort" label="Sort" type="number" min="0" :value="$option->sort ?? 0" span="md:col-span-1" />
    <x-admin.switch name="is_active" label="Active" :checked="(bool) $option->is_active" span="md:col-span-1" />
</x-admin.form-section>
