@php
    use App\Enums\BookingType;
@endphp
<x-admin.layout title="New booking">
    <x-admin.page-header title="New booking" :breadcrumbs="['Sales' => null, 'Bookings' => route('admin.bookings.index'), 'New booking' => null]"
                         subtitle="Priced by the same engine as the website: nationality group, season, children policy, and the partner commission when booked on behalf of a partner." />

    <form data-booking-editor data-mode="create" novalidate
          data-quote-url="{{ route('admin.bookings.quote') }}" data-submit-url="{{ route('admin.bookings.store') }}" data-submit-method="POST"
          data-options-url="{{ route('admin.bookings.product-options') }}"
          class="grid grid-cols-1 gap-4 xl:grid-cols-[minmax(0,1fr)_400px]">
        <div class="space-y-4">
            <x-admin.form-section title="Booked by">
                <fieldset class="md:col-span-6">
                    <legend class="form-label">Booker</legend>
                    <div class="flex flex-wrap gap-4 text-sm">
                        <label class="flex items-center gap-2"><input type="radio" name="booker" value="customer" checked> Existing customer or walk-in</label>
                        <label class="flex items-center gap-2"><input type="radio" name="booker" value="new_customer"> New customer account</label>
                        <label class="flex items-center gap-2"><input type="radio" name="booker" value="partner"> On behalf of a partner</label>
                    </div>
                </fieldset>
                <div class="md:col-span-6" data-show-when="input[name=booker]=customer">
                    <x-admin.select name="customer_id" label="Customer (optional)" :options="[]" placeholder="Search by name, e-mail or phone" data-s2-clear
                                    :data-s2-url="route('admin.bookings.customers')" hint="Leave empty for a walk-in: enter the lead traveller below." span="md:col-span-6" />
                </div>
                <div class="grid grid-cols-1 gap-5 md:col-span-6 md:grid-cols-6" data-show-when="input[name=booker]=new_customer" hidden>
                    <x-admin.input name="new_customer[name]" label="Name" span="md:col-span-2" />
                    <x-admin.input name="new_customer[email]" label="E-mail" type="email" span="md:col-span-2" />
                    <x-admin.input name="new_customer[phone]" label="Phone" span="md:col-span-2" />
                    <p class="form-hint md:col-span-6">The customer receives a "Welcome, set your password" e-mail with a link to the website (valid 7 days).</p>
                </div>
                <div class="md:col-span-6" data-show-when="input[name=booker]=partner" hidden>
                    <x-admin.select name="partner_id" label="Partner" :options="$options['partners']" placeholder="Choose an active partner" hint="Partner prices apply (commission per product type); the partner is the booker." span="md:col-span-6" />
                </div>
                <x-admin.input name="lead[name]" label="Lead traveller" hint="Defaults to the customer or partner contact." span="md:col-span-2" />
                <x-admin.input name="lead[email]" label="Lead e-mail" type="email" span="md:col-span-2" />
                <x-admin.input name="lead[phone]" label="Lead phone" span="md:col-span-2" />
                <x-admin.select name="nationality_code" label="Client nationality" :options="$options['countries']" placeholder="Choose the nationality" hint="Sets the nationality group of the price (CR-10)." span="md:col-span-3" />
                <x-admin.select name="currency" label="Currency" :options="$currencies" selected="EUR" :select2="false" span="md:col-span-1" />
                <x-admin.select name="locale" label="Language" :options="['en' => 'English', 'it' => 'Italiano']" selected="en" :select2="false" span="md:col-span-1" />
                <x-admin.switch name="notify" label="Send e-mails" description="Booking confirmation to the booker" :checked="true" span="md:col-span-1" />
            </x-admin.form-section>

            <x-admin.form-section title="Product">
                <x-admin.select name="product_type" label="Service" :options="['room' => 'Accommodation', 'trip' => 'Trip or Nile trip', 'package' => 'Package']" selected="room" :select2="false" span="md:col-span-2" />

                <div class="grid grid-cols-1 gap-5 md:col-span-6 md:grid-cols-6" data-show-when="#f-product_type=room">
                    <x-admin.select name="building_id" label="Building" :options="$buildings" placeholder="Choose a building" :select2="false" data-product-key="building" span="md:col-span-2" />
                    <x-admin.input name="check_in" label="Check-in" data-datepicker span="md:col-span-1" />
                    <x-admin.input name="check_out" label="Check-out" data-datepicker span="md:col-span-1" />
                    <div class="md:col-span-6">
                        <p class="form-label">Rooms and guests</p>
                        <p class="form-hint mb-2">One line per room. Every guest sleeps in exactly one room, each room needs an adult (SRS 7.2). Children ages comma separated, e.g. 4, 9.</p>
                        <div class="space-y-2" data-rows="rooms"></div>
                        <button type="button" class="btn-ghost mt-2" data-add-row="rooms">Add room</button>
                    </div>
                </div>

                <div class="grid grid-cols-1 gap-5 md:col-span-6 md:grid-cols-6" data-show-when="#f-product_type=trip" hidden>
                    <x-admin.select name="trip_slug" label="Trip" :options="$options['trips']" placeholder="Choose a trip" :select2="false" data-product-key="trip" span="md:col-span-6" />
                </div>
                <div class="grid grid-cols-1 gap-5 md:col-span-6 md:grid-cols-6" data-show-when="#f-product_type=package" hidden>
                    <x-admin.select name="package_slug" label="Package" :options="$options['packages']" placeholder="Choose a package" :select2="false" data-product-key="package" span="md:col-span-6" />
                </div>
                <div class="grid grid-cols-1 gap-5 md:col-span-6 md:grid-cols-6" data-trip-fields hidden>
                    <x-admin.select name="booking_type" label="Booking type" :options="BookingType::options()" :select2="false" span="md:col-span-2" />
                    <x-admin.input name="travel_date" label="Travel date" data-datepicker span="md:col-span-2" />
                    <x-admin.input name="adults" label="Adults" type="number" min="1" value="2" span="md:col-span-1" />
                    <x-admin.input name="child_ages" label="Children ages" placeholder="e.g. 4, 9" span="md:col-span-1" />
                    <div class="md:col-span-6" data-units hidden>
                        <p class="form-label">Units</p>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2" data-unit-list></div>
                    </div>
                    <div class="md:col-span-6" data-addons hidden>
                        <p class="form-label">Add-ons</p>
                        <div class="grid grid-cols-1 gap-2 sm:grid-cols-2" data-addon-list></div>
                    </div>
                    <div class="md:col-span-6" data-room-options hidden>
                        <p class="form-label">Optional rooms</p>
                        <p class="form-hint mb-2">Optional. When used, every guest must be in a room (per package stage).</p>
                        <div class="space-y-2" data-rows="room_options"></div>
                        <button type="button" class="btn-ghost mt-2" data-add-row="room_options">Add room</button>
                    </div>
                </div>
            </x-admin.form-section>

            <x-admin.form-section title="Notes">
                <x-admin.textarea name="notes" label="Internal notes" :rows="3" span="md:col-span-6" />
            </x-admin.form-section>
        </div>

        <aside class="space-y-4">
            <x-admin.card title="Price" class="xl:sticky xl:top-24">
                <div data-result aria-live="polite"><p class="text-sm text-ink-500">Fill in the product and the party, then calculate the price.</p></div>
                <div class="mt-4 flex flex-wrap gap-3">
                    <button type="button" class="btn-primary" data-action="quote">Calculate price</button>
                    <button type="button" class="btn-cta" data-action="submit">Create booking</button>
                </div>
            </x-admin.card>
        </aside>

        @include('admin.bookings.partials.editor-templates')
    </form>
</x-admin.layout>
