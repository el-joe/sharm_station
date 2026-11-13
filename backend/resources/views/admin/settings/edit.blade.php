<x-admin.layout title="Settings">
    <x-admin.page-header title="Settings" :breadcrumbs="['Settings' => null, 'General' => null]" subtitle="Company data and booking defaults.">
        <x-slot:actions><button type="submit" form="settings-form" class="btn-cta">Save settings</button></x-slot:actions>
    </x-admin.page-header>
    <form id="settings-form" method="POST" action="{{ route('admin.settings.update') }}" class="space-y-4" novalidate>
        @csrf @method('PUT')
        <x-admin.form-section title="Company">
            <x-admin.input name="site_name" label="Company name" :value="$values['site_name']" required span="md:col-span-3" />
            <x-admin.input name="site_email" label="Email" type="email" :value="$values['site_email']" span="md:col-span-3" />
            <x-admin.input name="site_phone" label="Phone" :value="$values['site_phone']" span="md:col-span-3" />
            <x-admin.input name="site_whatsapp" label="WhatsApp" :value="$values['site_whatsapp']" span="md:col-span-3" />
            <x-admin.input name="site_address" label="Address" :value="$values['site_address']" span="md:col-span-6" />
        </x-admin.form-section>
        <x-admin.form-section title="Bookings">
            <x-admin.input name="default_deposit_percent" label="Default deposit" type="number" step="0.01" min="0" max="100" suffix="%" :value="$values['default_deposit_percent']" hint="Pre-filled on new trips and buildings" span="md:col-span-2" />
            <x-admin.input name="hold_minutes" label="Room hold duration" type="number" min="5" suffix="min" :value="$values['hold_minutes']" hint="How long rooms stay held while awaiting payment" span="md:col-span-2" />
            <x-admin.input name="preview_commission" label="Commission preview" type="number" step="0.01" min="0" max="100" suffix="%" :value="$values['preview_commission']" hint="Only used for the partner price placeholder" span="md:col-span-2" />
        </x-admin.form-section>
        <x-admin.form-section title="Google reviews">
            <x-admin.input name="google_place_id" label="Business Google Place ID" :value="$values['google_place_id']" placeholder="ChIJ…" span="md:col-span-6" />
        </x-admin.form-section>
    </form>
</x-admin.layout>
