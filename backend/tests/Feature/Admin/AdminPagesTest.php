<?php

use App\Enums\UserRole;
use App\Models\Addon;
use App\Models\Booking;
use App\Models\Building;
use App\Models\Currency;
use App\Models\Destination;
use App\Models\Partner;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Trip;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = staffUser();
});

test('every admin screen renders for a super admin', function () {
    seedCurrencies();
    $trip = snorkellingTrip();
    $nile = Trip::factory()->nile()->create();
    $building = Building::factory()->create();
    $room = Room::factory()->for($building)->create();
    $booking = Booking::factory()->create(['partner_id' => Partner::factory()->create()->id]);
    $destination = Destination::factory()->create();
    $addon = Addon::factory()->create();

    $urls = [
        route('admin.dashboard'), route('admin.trips.index'), route('admin.trips.create'), route('admin.trips.create', ['type' => 'nile']),
        route('admin.trips.edit', $trip), route('admin.trips.edit', $nile), route('admin.destinations.index'), route('admin.destinations.create'),
        route('admin.destinations.edit', $destination), route('admin.addons.index'), route('admin.addons.create'), route('admin.addons.edit', $addon),
        route('admin.buildings.index'), route('admin.buildings.create'), route('admin.buildings.edit', $building), route('admin.rooms.index'),
        route('admin.rooms.create'), route('admin.rooms.edit', $room), route('admin.rooms.calendar', [$room, 'month' => '2027-02']),
        route('admin.bookings.index'), route('admin.bookings.show', $booking), route('admin.partners.index'), route('admin.partners.commissions.edit'),
        route('admin.google-reviews.index'), route('admin.currencies.index'), route('admin.settings.edit'), route('admin.soon', 'packages'),
    ];

    foreach ($urls as $url) {
        $this->actingAs($this->admin)->get($url)->assertOk();
    }

    $this->actingAs($this->admin)->get(route('admin.trips.edit', $nile))->assertSee('Vessel name')->assertDontSee('Cabin deck');
    $this->actingAs($this->admin)->get(route('admin.trips.edit', $trip))->assertDontSee('Vessel name')->assertSee('Private 1–2 pax');
});

test('every DataTables endpoint answers with the DataTables JSON shape', function () {
    Trip::factory()->create();
    Destination::factory()->create();
    Addon::factory()->create();
    Room::factory()->create();
    Partner::factory()->create();

    foreach (['trips', 'destinations', 'addons', 'buildings', 'rooms', 'bookings', 'partners'] as $table) {
        $this->actingAs($this->admin)->getJson(route("admin.{$table}.data", ['draw' => 1, 'start' => 0, 'length' => 10]))
            ->assertOk()->assertJsonStructure(['draw', 'recordsTotal', 'recordsFiltered', 'data']);
    }
});

test('editors do not see price columns or prices in lists', function () {
    Trip::factory()->create(['from_price' => 123.45]);

    $row = $this->actingAs(staffUser(UserRole::Editor))->getJson(route('admin.trips.data', ['draw' => 1, 'start' => 0, 'length' => 10]))->json('data.0');

    expect($row['from_price'])->toBe('—');
    $this->actingAs(staffUser(UserRole::Editor))->get(route('admin.addons.create'))->assertForbidden();
});

test('destinations are created, updated and protected from deletion while in use', function () {
    $this->actingAs($this->admin)->post(route('admin.destinations.store'), ['name' => ['en' => 'Dahab', 'it' => 'Dahab'], 'is_active' => '1', 'sort' => 4])
        ->assertRedirect(route('admin.destinations.index'));
    $destination = Destination::query()->where('slug', 'dahab')->firstOrFail();

    $this->actingAs($this->admin)->put(route('admin.destinations.update', $destination), ['name' => ['en' => 'Dahab Blue Hole'], 'slug' => 'dahab', 'is_active' => '0'])
        ->assertSessionHasNoErrors();
    expect((string) $destination->fresh()->name)->toBe('Dahab Blue Hole')->and($destination->fresh()->is_active)->toBeFalse();

    Trip::factory()->create(['destination_id' => $destination->id]);
    $this->actingAs($this->admin)->delete(route('admin.destinations.destroy', $destination))->assertSessionHasErrors('destination');
    expect(Destination::query()->find($destination->id))->not->toBeNull();
});

test('add-ons are created with booking types and validated partner price', function () {
    $this->actingAs($this->admin)->post(route('admin.addons.store'), [
        'name' => ['en' => 'Hotel pick-up'], 'price' => 10, 'partner_price' => 8, 'pricing_basis' => 'per_booking',
        'max_quantity' => 1, 'booking_types' => ['private'], 'is_active' => '1',
    ])->assertRedirect(route('admin.addons.index'));

    $addon = Addon::query()->latest('id')->firstOrFail();
    expect($addon->booking_types)->toBe(['private'])->and((float) $addon->partner_price)->toBe(8.0);

    $this->actingAs($this->admin)->put(route('admin.addons.update', $addon), [
        'name' => ['en' => 'Hotel pick-up'], 'price' => 10, 'partner_price' => 12, 'pricing_basis' => 'per_booking', 'max_quantity' => 1,
    ])->assertSessionHasErrors(['partner_price' => 'The partner price cannot be higher than the retail price.']);
});

test('currencies and settings are saved through their services', function () {
    seedCurrencies();
    $eur = Currency::query()->where('code', 'EUR')->firstOrFail();
    $egp = Currency::query()->where('code', 'EGP')->firstOrFail();

    $this->actingAs($this->admin)->put(route('admin.currencies.update'), ['currencies' => [
        $eur->id => ['rate_to_eur' => 5, 'margin_percent' => 3, 'is_enabled' => '0'],
        $egp->id => ['rate_to_eur' => 52.5, 'margin_percent' => 1.5, 'is_enabled' => '0', 'is_manual_rate' => '1'],
    ]])->assertRedirect(route('admin.currencies.index'));

    expect((float) $eur->fresh()->rate_to_eur)->toBe(1.0)->and($eur->fresh()->is_enabled)->toBeTrue()
        ->and((float) $egp->fresh()->rate_to_eur)->toBe(52.5)->and($egp->fresh()->is_enabled)->toBeFalse();

    $this->actingAs($this->admin)->put(route('admin.settings.update'), [
        'site_name' => 'Sharm Station Viaggi', 'site_email' => 'info@example.test', 'default_deposit_percent' => 25,
        'hold_minutes' => 45, 'google_place_id' => 'ChIJ-new-place', 'preview_commission' => 12,
    ])->assertRedirect(route('admin.settings.edit'));

    expect(Setting::query()->where('key', 'accommodation.hold_minutes')->value('value'))->toBe(45)
        ->and(Setting::query()->where('key', 'google_reviews.place_id')->value('value'))->toBe('ChIJ-new-place');
});
