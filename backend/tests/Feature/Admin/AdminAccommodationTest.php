<?php

use App\Enums\RoomAvailabilityStatus;
use App\Enums\UserRole;
use App\Models\Building;
use App\Models\Destination;
use App\Models\Room;
use App\Models\RoomAvailability;
use App\Models\SeasonPrice;
use Illuminate\Support\Facades\Http;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = staffUser();
    $this->destination = Destination::factory()->create();
});

function buildingPayload(Destination $destination, array $overrides = []): array
{
    return [
        'name' => ['en' => 'Hotel Nile View', 'it' => 'Hotel Vista Nilo'],
        'type' => 'hotel',
        'destination_id' => $destination->id,
        'status' => 'published',
        'address' => 'Corniche, Luxor',
        'stars' => 4,
        'amenities' => ['wifi', 'rooftop_terrace'],
        'check_in_from' => '14:00',
        'check_out_until' => '12:00',
        'deposit_percent' => 30,
        'taxes_included' => '1',
        'google_place_id' => 'ChIJ-nile-view',
        ...$overrides,
    ];
}

test('a building is created with taxes included, custom amenity tags and a Google Place ID', function () {
    $this->actingAs($this->admin)->post(route('admin.buildings.store'), buildingPayload($this->destination, ['tax_per_night' => 9]))
        ->assertSessionHasNoErrors();

    $building = Building::query()->where('slug', 'hotel-nile-view')->firstOrFail();
    expect($building->taxes_included)->toBeTrue()
        ->and($building->tax_per_night)->toBeNull()
        ->and($building->amenities)->toBe(['wifi', 'rooftop_terrace'])
        ->and($building->google_place_id)->toBe('ChIJ-nile-view');
});

test('turning taxes included off requires and stores the tax per night', function () {
    $building = Building::factory()->create(['destination_id' => $this->destination->id]);

    $this->actingAs($this->admin)
        ->put(route('admin.buildings.update', $building), buildingPayload($this->destination, ['slug' => $building->slug, 'taxes_included' => '0']))
        ->assertSessionHasErrors(['tax_per_night' => 'Enter the tax per night, or switch "Taxes included" on.']);

    $this->actingAs($this->admin)
        ->put(route('admin.buildings.update', $building), buildingPayload($this->destination, ['slug' => $building->slug, 'taxes_included' => '0', 'tax_per_night' => 4.5]))
        ->assertSessionHasNoErrors();

    expect($building->fresh()->taxes_included)->toBeFalse()
        ->and((float) $building->fresh()->tax_per_night)->toBe(4.5);
});

test('an invalid Google Place ID is rejected', function () {
    $this->actingAs($this->admin)
        ->post(route('admin.buildings.store'), buildingPayload($this->destination, ['google_place_id' => 'bad id <script>']))
        ->assertSessionHasErrors('google_place_id');
});

test('editors cannot change building taxes or deposit', function () {
    $building = Building::factory()->taxesExcluded(5)->create(['destination_id' => $this->destination->id]);

    $this->actingAs(staffUser(UserRole::Editor))
        ->put(route('admin.buildings.update', $building), buildingPayload($this->destination, ['slug' => $building->slug, 'taxes_included' => '1', 'deposit_percent' => 0]))
        ->assertSessionHasNoErrors();

    expect($building->fresh()->taxes_included)->toBeFalse()
        ->and((float) $building->fresh()->tax_per_night)->toBe(5.0)
        ->and((float) $building->fresh()->deposit_percent)->toBe(30.0);
});

test('"Sync now" reads the building reviews through GoogleReviewService', function () {
    config(['services.google.places_key' => 'test-key']);
    Http::fake(['places.googleapis.com/*' => Http::response([
        'rating' => 4.6,
        'userRatingCount' => 120,
        'reviews' => [[
            'name' => 'places/ChIJ-nile-view/reviews/r1',
            'rating' => 5,
            'originalText' => ['text' => 'Lovely terrace', 'languageCode' => 'en'],
            'authorAttribution' => ['displayName' => 'Anna'],
            'publishTime' => '2026-10-01T10:00:00Z',
        ]],
    ])]);
    $building = Building::factory()->create(['google_place_id' => 'ChIJ-nile-view']);

    $this->actingAs($this->admin)->postJson(route('admin.buildings.reviews.sync', $building))
        ->assertOk()
        ->assertJson(['count' => 1, 'message' => 'Synced 1 Google review.']);

    $this->assertDatabaseHas('google_reviews', ['place_id' => 'ChIJ-nile-view', 'author_name' => 'Anna']);
});

test('"Sync now" explains when the building has no Place ID', function () {
    $building = Building::factory()->create(['google_place_id' => null]);

    $this->actingAs($this->admin)->postJson(route('admin.buildings.reviews.sync', $building))
        ->assertUnprocessable()
        ->assertJsonPath('errors.google_place_id.0', 'Save a Google Place ID for this building first.');
});

test('rooms are created and get a price list per night', function () {
    $building = Building::factory()->create();

    $this->actingAs($this->admin)->post(route('admin.rooms.store'), [
        'building_id' => $building->id,
        'name' => ['en' => 'Deluxe Nile view'],
        'room_type' => 'double',
        'status' => 'active',
        'max_adults' => 2,
        'max_children' => 1,
        'max_total' => 3,
        'pricing_mode' => 'per_room',
        'base_price' => 85,
        'min_nights' => 2,
        'amenities' => ['wifi'],
    ])->assertSessionHasNoErrors();

    $room = Room::query()->latest('id')->firstOrFail();
    expect((float) $room->base_price)->toBe(85.0)->and($room->min_nights)->toBe(2);

    $this->actingAs($this->admin)->put(route('admin.rooms.seasons.update', $room), ['seasons' => [
        ['name' => ['en' => 'Winter'], 'starts_on' => now()->addMonth()->toDateString(), 'ends_on' => now()->addMonths(2)->toDateString(), 'is_active' => '1', 'price_per_night' => 60],
    ]])->assertSessionHasNoErrors();

    expect(SeasonPrice::query()->whereMorphedTo('priceable', $room)->value('price_per_night'))->toEqual('60.00')
        ->and((float) $room->fresh()->from_price)->toBe(60.0);
});

test('the room calendar closes and reopens dates but leaves booked nights alone', function () {
    $room = Room::factory()->create();
    $date = now()->addDays(3)->toDateString();
    $booked = now()->addDays(4)->toDateString();
    RoomAvailability::factory()->create(['room_id' => $room->id, 'date' => $booked, 'status' => RoomAvailabilityStatus::Booked]);

    $this->actingAs($this->admin)->get(route('admin.rooms.calendar', $room))->assertOk()->assertSee('Availability calendar');

    $this->actingAs($this->admin)->postJson(route('admin.rooms.availability.update', $room), ['dates' => [$date], 'status' => 'closed'])
        ->assertOk()->assertJson(['status' => 'closed', 'changed' => 1]);
    expect($room->availability()->where('date', $date)->value('status'))->toBe(RoomAvailabilityStatus::Closed);

    $this->actingAs($this->admin)->postJson(route('admin.rooms.availability.update', $room), ['dates' => [$booked], 'status' => 'closed'])
        ->assertOk()->assertJson(['changed' => 0]);
    expect($room->availability()->where('date', $booked)->value('status'))->toBe(RoomAvailabilityStatus::Booked);

    $this->actingAs($this->admin)->postJson(route('admin.rooms.availability.update', $room), ['dates' => [$date], 'status' => 'open'])
        ->assertOk()->assertJson(['status' => 'open']);
    expect($room->availability()->where('date', $date)->exists())->toBeFalse();

    $this->actingAs($this->admin)->postJson(route('admin.rooms.availability.update', $room), ['dates' => [$date], 'status' => 'booked'])
        ->assertUnprocessable();
});
