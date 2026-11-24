<?php

use App\Enums\BookingType;
use App\Enums\PricingMode;
use App\Enums\TripType;
use App\Enums\UserRole;
use App\Models\ActivityLog;
use App\Models\Addon;
use App\Models\Destination;
use App\Models\SeasonPrice;
use App\Models\Trip;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = staffUser();
    $this->destination = Destination::factory()->create();
});

function perUnitTripPayload(Destination $destination, array $overrides = []): array
{
    return [
        'type' => TripType::SingleDay->value,
        'destination_id' => $destination->id,
        'title' => ['en' => 'Quad bike sunset', 'it' => 'Quad al tramonto'],
        'summary' => ['en' => 'Ride into the desert'],
        'status' => 'published',
        'featured' => '1',
        'private_enabled' => '1',
        'group_enabled' => '0',
        'days' => 1,
        'nights' => 0,
        'pricing_mode' => PricingMode::PerUnit->value,
        'min_persons' => 1,
        'max_persons' => 8,
        'deposit_percent' => 25,
        'unit_options' => [
            'n1' => ['name' => ['en' => 'Single quad', 'it' => 'Quad singolo'], 'seats' => 1, 'price' => 40, 'max_quantity' => 6, 'children_allowed' => '0', 'sort' => 0],
            'n2' => ['name' => ['en' => 'Double quad'], 'seats' => 2, 'price' => 60, 'max_quantity' => 4, 'children_allowed' => '1', 'sort' => 1],
        ],
        'availability' => [
            'private' => ['lead_time_hours' => 12, 'blackout_dates' => '2026-12-25, 2026-12-31'],
            'group' => ['lead_time_hours' => 24, 'weekdays' => ['1', '5'], 'dates' => '', 'blackout_dates' => ''],
        ],
        'itinerary_present' => '1',
        'itinerary' => [
            'd1' => ['title' => ['en' => 'Afternoon ride'], 'items' => [
                'i1' => ['time_from' => '15:00', 'time_to' => '16:00', 'title' => ['en' => 'Briefing'], 'place' => 'Camp'],
                'i2' => ['time_from' => '16:00', 'title' => ['en' => 'Ride to the canyon']],
            ]],
        ],
        'addons_present' => '1',
        'seo' => ['title' => 'Quad bike sunset in Sharm'],
        ...$overrides,
    ];
}

test('the trips list and its DataTables endpoint return trips of the requested scope', function () {
    Trip::factory()->create(['title' => ['en' => 'Snorkel day'], 'slug' => 'snorkel-day']);
    Trip::factory()->nile()->create(['title' => ['en' => 'Luxor to Aswan cruise'], 'slug' => 'nile-cruise']);

    $this->actingAs($this->admin)->get(route('admin.trips.index'))->assertOk()->assertSee('New trip');
    $this->actingAs($this->admin)->get(route('admin.trips.index', ['type' => 'nile']))->assertOk()->assertSee('New Nile trip');

    $response = $this->actingAs($this->admin)->getJson(route('admin.trips.data', ['scope' => 'nile', 'draw' => 1, 'start' => 0, 'length' => 10]));

    $response->assertOk()->assertJsonPath('recordsTotal', 1)->assertJsonPath('draw', 1);
    expect($response->json('data.0.title'))->toContain('Luxor to Aswan cruise');
});

test('a per-unit trip is created with unit options, availability, itinerary and add-ons', function () {
    $addons = Addon::factory()->count(2)->create();

    $response = $this->actingAs($this->admin)->post(route('admin.trips.store'), perUnitTripPayload($this->destination, [
        'addon_ids' => [$addons[1]->id, $addons[0]->id],
    ]));

    $trip = Trip::query()->where('slug', 'quad-bike-sunset')->firstOrFail();
    $response->assertRedirect(route('admin.trips.edit', $trip))->assertSessionHas('success');

    expect($trip->pricing_mode)->toBe(PricingMode::PerUnit)
        ->and($trip->featured)->toBeTrue()
        ->and($trip->seo['title'])->toBe('Quad bike sunset in Sharm')
        ->and($trip->unitOptions)->toHaveCount(2)
        ->and($trip->unitOptions[0]->getTranslations('name'))->toBe(['en' => 'Single quad', 'it' => 'Quad singolo'])
        ->and($trip->unitOptions[0]->children_allowed)->toBeFalse()
        ->and((float) $trip->unitOptions[1]->price)->toBe(60.0)
        ->and((float) $trip->from_price)->toBe(40.0)
        ->and($trip->addons->pluck('id')->all())->toBe([$addons[1]->id, $addons[0]->id]);

    $group = $trip->availabilityRules->firstWhere('booking_type', BookingType::Group);
    $private = $trip->availabilityRules->firstWhere('booking_type', BookingType::Private);
    expect($group->weekdays)->toBe([1, 5])
        ->and($private->blackout_dates)->toBe(['2026-12-25', '2026-12-31'])
        ->and($private->lead_time_hours)->toBe(12);

    $day = $trip->tripDays()->with('items')->first();
    expect($day->day_number)->toBe(1)
        ->and($day->items->pluck('title')->all())->toBe(['Briefing', 'Ride to the canyon']);
});

test('updating a trip updates, reorders and removes unit options', function () {
    $trip = buggyTrip(['destination_id' => $this->destination->id]);
    [$single, $double, $family] = $trip->unitOptions->all();

    $payload = perUnitTripPayload($this->destination, [
        'title' => ['en' => 'Desert buggy safari'],
        'slug' => 'desert-buggy-safari',
        'unit_options' => [
            'a' => ['id' => $family->id, 'name' => ['en' => 'Family buggy'], 'seats' => 4, 'price' => 95, 'max_quantity' => 5, 'children_allowed' => '1', 'sort' => 0],
            'b' => ['id' => $single->id, 'name' => ['en' => 'Single buggy'], 'seats' => 1, 'price' => 36, 'max_quantity' => 4, 'children_allowed' => '0', 'sort' => 1],
        ],
    ]);

    $this->actingAs($this->admin)->put(route('admin.trips.update', $trip), $payload)->assertSessionHasNoErrors()->assertRedirect();

    $options = $trip->fresh()->unitOptions;
    expect($options->pluck('id')->all())->toBe([$family->id, $single->id])
        ->and((float) $options[0]->price)->toBe(95.0)
        ->and($options->contains('id', $double->id))->toBeFalse();
});

test('a per-unit trip needs at least one unit option and ignores partner prices', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.trips.store'), perUnitTripPayload($this->destination, ['unit_options' => []]))
        ->assertSessionHasErrors(['unit_options' => 'Add at least one unit option for a per-unit trip.']);

    $this->actingAs($this->admin)
        ->post(route('admin.trips.store'), perUnitTripPayload($this->destination, ['unit_options' => [
            ['name' => ['en' => 'Single'], 'seats' => 1, 'price' => 30, 'partner_price' => 45, 'max_quantity' => 3],
        ]]))
        ->assertSessionDoesntHaveErrors('unit_options.0.partner_price');

    $this->actingAs($this->admin)
        ->post(route('admin.trips.store'), perUnitTripPayload($this->destination, ['private_enabled' => '0', 'group_enabled' => '0']))
        ->assertSessionHasErrors(['private_enabled' => 'Enable at least one booking type (private or group).']);
});

test('editors can edit content but their price input is ignored', function () {
    $trip = buggyTrip(['destination_id' => $this->destination->id, 'deposit_percent' => 30]);

    $payload = perUnitTripPayload($this->destination, [
        'title' => ['en' => 'Renamed buggy safari'],
        'slug' => 'desert-buggy-safari',
        'deposit_percent' => 1,
        'pricing_mode' => PricingMode::PerPerson->value,
        'unit_options' => [['name' => ['en' => 'Hacked'], 'seats' => 1, 'price' => 1, 'max_quantity' => 1]],
    ]);

    $this->actingAs(staffUser(UserRole::Editor))->put(route('admin.trips.update', $trip), $payload)->assertSessionHasNoErrors();

    $trip->refresh();
    expect((string) $trip->title)->toBe('Renamed buggy safari')
        ->and((float) $trip->deposit_percent)->toBe(30.0)
        ->and($trip->pricing_mode)->toBe(PricingMode::PerUnit)
        ->and($trip->unitOptions)->toHaveCount(3);
});

test('the price list rejects overlapping seasons with the exact message on the offending row', function () {
    $trip = buggyTrip(['destination_id' => $this->destination->id]);
    $option = $trip->unitOptions->first();

    $response = $this->actingAs($this->admin)
        ->from(route('admin.trips.edit', $trip))
        ->put(route('admin.trips.seasons.update', $trip), ['seasons' => [
            'n1' => ['name' => ['en' => 'High season'], 'starts_on' => '2026-11-01', 'ends_on' => '2026-12-19', 'is_active' => '1', 'recurring_yearly' => '0',
                'unit_prices' => [$option->id => 40]],
            'n2' => ['name' => ['en' => 'Christmas & New Year'], 'starts_on' => '2026-12-15', 'ends_on' => '2027-01-06', 'is_active' => '1', 'recurring_yearly' => '0',
                'unit_prices' => [$option->id => 45]],
        ]]);

    $response->assertRedirect(route('admin.trips.edit', $trip))
        ->assertSessionHasErrors(['seasons.1.starts_on' => '“Christmas & New Year” overlaps “High season” on 15–19 Dec 2026.']);

    expect(SeasonPrice::query()->count())->toBe(0);

    $this->actingAs($this->admin)->get(route('admin.trips.edit', $trip))->assertOk();
});

test('the price list saves, updates and deletes seasons and refreshes the from price', function () {
    $trip = buggyTrip(['destination_id' => $this->destination->id]);
    $option = $trip->unitOptions->first();
    $old = SeasonPrice::factory()->for($trip, 'priceable')->between('2027-01-07', '2027-03-31', 'Low season')->create();
    $removed = SeasonPrice::factory()->for($trip, 'priceable')->between('2027-04-01', '2027-06-30', 'Spring')->create();

    $this->actingAs($this->admin)->put(route('admin.trips.seasons.update', $trip), ['seasons' => [
        ['id' => $old->id, 'name' => ['en' => 'Low season', 'it' => 'Bassa stagione'], 'starts_on' => '2027-01-07', 'ends_on' => '2027-03-31', 'is_active' => '1', 'recurring_yearly' => '1',
            'unit_prices' => [$option->id => 25]],
        ['name' => ['en' => 'Christmas'], 'starts_on' => '2026-12-20', 'ends_on' => '2027-01-06', 'is_active' => '1', 'recurring_yearly' => '0',
            'unit_prices' => [$option->id => 45]],
    ]])->assertSessionHasNoErrors()->assertRedirect(route('admin.trips.edit', $trip).'#tab-prices');

    $seasons = SeasonPrice::query()->orderBy('starts_on')->get();
    expect($seasons)->toHaveCount(2)
        ->and(SeasonPrice::query()->find($removed->id))->toBeNull()
        ->and($old->fresh()->recurring_yearly)->toBeTrue()
        ->and($old->fresh()->unitPriceFor($option->id))->toBe(25.0)
        ->and((float) $trip->fresh()->from_price)->toBe(25.0);

    // The save is in the activity log (A7).
    expect(ActivityLog::query()->where('subject_type', 'trip')->where('subject_id', $trip->id)->latest('id')->first())
        ->description->toBe('Price matrix of trip “'.$trip->title.'” saved')
        ->properties->toMatchArray(['seasons' => 2]);
});

test('a season can be copied to the next year', function () {
    $trip = buggyTrip(['destination_id' => $this->destination->id]);
    $season = SeasonPrice::factory()->for($trip, 'priceable')->between('2026-11-01', '2026-12-19', 'High season')->create();

    $this->actingAs($this->admin)->post(route('admin.seasons.copy-next-year', $season))
        ->assertRedirect(route('admin.trips.edit', $trip).'#tab-prices')
        ->assertSessionHas('success');

    expect(SeasonPrice::query()->where('starts_on', '2027-11-01')->exists())->toBeTrue();
});

test('editors cannot change the price list', function () {
    $trip = buggyTrip(['destination_id' => $this->destination->id]);

    $this->actingAs(staffUser(UserRole::Editor))->put(route('admin.trips.seasons.update', $trip), ['seasons' => []])->assertForbidden();
});

test('a trip can be deleted', function () {
    $trip = Trip::factory()->create();

    $this->actingAs($this->admin)->delete(route('admin.trips.destroy', $trip))->assertRedirect(route('admin.trips.index'));

    expect(Trip::query()->find($trip->id))->toBeNull();
});
