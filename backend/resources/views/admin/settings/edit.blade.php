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
        </x-admin.form-section>
        <x-admin.form-section title="Office location" description="Address and map pin of the office, shown on the website contact page.">
            <x-admin.location-picker address-name="site_address" lat-name="site_lat" lng-name="site_lng" address-label="Office address"
                                     :address="$values['site_address']" :lat="$values['site_lat']" :lng="$values['site_lng']" search-label="Search the office address" />
        </x-admin.form-section>
        <x-admin.form-section title="Bookings">
            <x-admin.input name="default_deposit_percent" label="Default deposit" type="number" step="0.01" min="0" max="100" suffix="%" :value="$values['default_deposit_percent']" hint="Pre-filled on new trips and buildings" span="md:col-span-2" />
            <x-admin.input name="hold_minutes" label="Room hold duration" type="number" min="5" suffix="min" :value="$values['hold_minutes']" hint="How long rooms stay held while awaiting payment" span="md:col-span-2" />
            <x-admin.input name="preview_commission" label="Commission preview" type="number" step="0.01" min="0" max="100" suffix="%" :value="$values['preview_commission']" hint="Only used for the partner price placeholder" span="md:col-span-2" />
        </x-admin.form-section>
        <x-admin.form-section title="Notifications" description="Emails of SRS 15.1. Templates and the sending log are in Notifications.">
            <x-admin.textarea name="admin_emails" label="Admin recipients" :value="$values['admin_emails']" :rows="2" hint="Comma-separated. Empty = the company email." span="md:col-span-6" />
            <x-admin.input name="balance_reminder_days" label="Balance reminder" type="number" min="0" max="60" suffix="days before" :value="$values['balance_reminder_days']" hint="0 disables the reminder" span="md:col-span-2" />
            <x-admin.input name="balance_due_days_before" label="Balance due" type="number" min="0" max="60" suffix="days before service" :value="$values['balance_due_days_before']" hint="Unless set on the booking" span="md:col-span-2" />
            <x-admin.input name="hold_warning_minutes" label="Hold expiry warning" type="number" min="1" max="720" suffix="min before" :value="$values['hold_warning_minutes']" hint="Email before a room hold expires" span="md:col-span-2" />
        </x-admin.form-section>
        <x-admin.form-section title="Exchange rates and security">
            <x-admin.input name="exchange_rate_refresh_hours" label="Refresh rates every" type="number" min="0" max="168" suffix="hours" :value="$values['exchange_rate_refresh_hours']" hint="0 = manual only. Manual rates are never overwritten." span="md:col-span-3" />
            <x-admin.switch name="require_two_factor" label="Require two-factor authentication for super admins" :checked="$values['require_two_factor']" description="Super admins without 2FA must enrol after signing in." span="md:col-span-3" />
        </x-admin.form-section>
        <x-admin.form-section title="Google reviews">
            <x-admin.input name="google_place_id" label="Business Google Place ID" :value="$values['google_place_id']" placeholder="ChIJ…" span="md:col-span-6" />
        </x-admin.form-section>
    </form>
</x-admin.layout>
