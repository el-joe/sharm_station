@php
    $title = $method->exists ? (string) $method->name : 'New payment method';
    $details = old('account_details') !== null ? array_values(old('account_details')) : ($method->account_details ?? []);
@endphp
<x-admin.layout :title="$title">
    <x-admin.page-header :title="$title" :breadcrumbs="['Settings' => null, 'Payment methods' => route('admin.payment-methods.index'), $title => null]">
        <x-slot:actions><button type="submit" form="method-form" class="btn-cta">Save</button></x-slot:actions>
    </x-admin.page-header>
    <form id="method-form" method="POST" action="{{ $method->exists ? route('admin.payment-methods.update', $method) : route('admin.payment-methods.store') }}" novalidate class="space-y-4">
        @csrf
        @if ($method->exists) @method('PUT') @endif
        <x-admin.form-section title="Method" description="Gateway keys are read from the server environment (.env), never entered here.">
            <x-admin.translatable name="name" label="Name" :value="$method->getTranslations('name')" required-locale="en" span="md:col-span-4" />
            <x-admin.input name="code" label="Code" :value="$method->code" hint="Leave empty to generate." span="md:col-span-2" />
            <x-admin.select name="type" label="Type" :options="\App\Enums\PaymentMethodType::options()" :selected="$method->type" :select2="false" span="md:col-span-2" />
            <x-admin.select name="driver" label="Gateway driver (online)" :options="array_combine($drivers, $drivers)" :selected="$method->driver" placeholder="—" :select2="false" span="md:col-span-2" />
            <x-admin.select name="mode" label="Mode" :options="['test' => 'Test', 'live' => 'Live']" :selected="$method->mode ?? 'test'" :select2="false" span="md:col-span-2" />
            <x-admin.select name="currencies[]" label="Currencies" :options="\App\Enums\Currency::options()" :selected="$method->currencies ?? []" multiple placeholder="All currencies" span="md:col-span-3" />
            <x-admin.input name="surcharge_percent" label="Surcharge" type="number" step="0.01" min="0" suffix="%" :value="$method->surcharge_percent ?? 0" span="md:col-span-1" />
            <x-admin.input name="deadline_hours" label="Payment deadline (h)" type="number" min="1" :value="$method->deadline_hours" span="md:col-span-1" />
            <x-admin.input name="sort" label="Order" type="number" min="0" :value="$method->sort ?? 0" span="md:col-span-1" />
            <x-admin.switch name="proof_required" label="Proof of payment required" :checked="$method->proof_required" span="md:col-span-3" />
            <x-admin.switch name="is_enabled" label="Enabled" :checked="$method->is_enabled" span="md:col-span-3" />
            <x-admin.translatable name="instructions" label="Instructions to the customer" type="textarea" :rows="3" :value="$method->getTranslations('instructions')" />
        </x-admin.form-section>
        <x-admin.card title="Transfer details" subtitle="Shown to the customer (account name, IBAN, InstaPay address, bank...).">
            <div data-repeater>
                <div data-repeater-list>
                    @foreach ($details as $index => $row)
                        <div data-repeater-row class="grid grid-cols-[1fr_2fr_40px] items-end gap-3 border-b border-ink-100 py-2">
                            <div><label class="form-label text-xs">Label</label><input type="text" name="account_details[{{ $index }}][label]" value="{{ $row['label'] ?? '' }}" class="form-control"></div>
                            <div><label class="form-label text-xs">Value</label><input type="text" name="account_details[{{ $index }}][value]" value="{{ $row['value'] ?? '' }}" class="form-control"></div>
                            <button type="button" data-repeater-remove class="rounded-control p-2 text-ink-500 hover:text-error" aria-label="Remove row"><x-admin.icon name="trash" size="18" /></button>
                        </div>
                    @endforeach
                </div>
                <template data-repeater-template>
                    <div data-repeater-row class="grid grid-cols-[1fr_2fr_40px] items-end gap-3 border-b border-ink-100 py-2">
                        <div><label class="form-label text-xs">Label</label><input type="text" name="account_details[__INDEX__][label]" class="form-control"></div>
                        <div><label class="form-label text-xs">Value</label><input type="text" name="account_details[__INDEX__][value]" class="form-control"></div>
                        <button type="button" data-repeater-remove class="rounded-control p-2 text-ink-500 hover:text-error" aria-label="Remove row"><x-admin.icon name="trash" size="18" /></button>
                    </div>
                </template>
                <button type="button" data-repeater-add class="btn-primary mt-3"><x-admin.icon name="plus" size="18" /> Add row</button>
            </div>
        </x-admin.card>
    </form>
</x-admin.layout>
