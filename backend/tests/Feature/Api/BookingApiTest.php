<?php

use App\Models\Booking;
use App\Models\Partner;
use App\Models\Room;
use App\Models\Setting;
use App\Models\Trip;
use App\Models\User;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    seedCurrencies();
    Setting::factory()->create(['key' => 'payment.methods', 'value' => [
        ['code' => 'stripe', 'type' => 'online', 'name' => 'Card', 'currencies' => ['EUR', 'USD']],
        ['code' => 'instapay', 'type' => 'manual', 'name' => 'InstaPay', 'currencies' => ['EGP']],
        ['code' => 'bank_transfer', 'type' => 'manual', 'name' => 'Bank transfer', 'currencies' => []],
    ]]);
});

function bookingBody(Trip $trip, array $overrides = []): array
{
    return [
        'trip_slug' => $trip->slug,
        'booking_type' => 'group',
        'travel_date' => '2026-11-25',
        'adults' => 3,
        'units' => [
            ['unit_option_id' => $trip->unitOptions[0]->id, 'quantity' => 1],
            ['unit_option_id' => $trip->unitOptions[1]->id, 'quantity' => 1],
        ],
        'lead' => ['name' => 'Giulia Rossi', 'email' => 'giulia@example.com', 'phone' => '+39 333 1234567'],
        'travelers' => [
            ['first_name' => 'Giulia', 'last_name' => 'Rossi', 'type' => 'adult'],
            ['first_name' => 'Paolo', 'last_name' => 'Rossi', 'type' => 'adult'],
            ['first_name' => 'Anna', 'last_name' => 'Rossi', 'type' => 'adult'],
        ],
        ...$overrides,
    ];
}

test('a trip booking is created awaiting payment with its reference, totals and payment options', function () {
    $trip = buggyTrip();

    $response = $this->postJson('/api/v1/bookings', bookingBody($trip))
        ->assertCreated()
        ->assertJsonPath('data.reference', 'BK-2041')
        ->assertJsonPath('data.status', 'awaiting_payment')
        ->assertJsonPath('data.payment_status', 'awaiting_payment')
        ->assertJsonPath('data.service_type', 'trip')
        ->assertJsonPath('data.pricing_mode', 'per_unit')
        ->assertJsonPath('data.totals.total', 85)
        ->assertJsonPath('data.totals.deposit_amount', 25.5)
        ->assertJsonCount(2, 'data.units')
        ->assertJsonCount(3, 'data.travelers')
        ->assertJsonPath('data.payment_options.*.code', ['stripe', 'bank_transfer']);

    expect($response->json('data.access_token'))->toBeString()
        ->and(Booking::query()->sole()->lead_email)->toBe('giulia@example.com');
});

test('a booking is linked to the signed in customer', function () {
    $trip = buggyTrip();
    $user = User::factory()->create();
    Sanctum::actingAs($user);

    $this->postJson('/api/v1/bookings', bookingBody($trip))->assertCreated();

    expect(Booking::query()->sole()->user_id)->toBe($user->id);
});

test('an accommodation booking can be created from a stay payload', function () {
    $room = Room::factory()->create(['base_price' => 80]);

    $this->postJson('/api/v1/bookings?currency=USD', [
        'room_id' => $room->id,
        'check_in' => '2026-12-01',
        'check_out' => '2026-12-03',
        'adults' => 1,
        'lead' => ['name' => 'John Smith', 'email' => 'john@example.com'],
        'travelers' => [['first_name' => 'John', 'last_name' => 'Smith', 'type' => 'adult']],
    ])
        ->assertCreated()
        ->assertJsonPath('data.service_type', 'accommodation')
        ->assertJsonPath('data.check_out', '2026-12-03')
        ->assertJsonPath('data.totals.total', 160)
        ->assertJsonPath('data.totals.currency', 'USD')
        ->assertJsonPath('data.totals.total_in_currency', 172.8)
        ->assertJsonPath('data.rooms.0.nights', 2);
});

test('a booking that breaks the pricing rules is rejected', function () {
    $trip = buggyTrip();

    $this->postJson('/api/v1/bookings', bookingBody($trip, ['units' => [['unit_option_id' => $trip->unitOptions[1]->id, 'quantity' => 1]]]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['units']);

    $this->postJson('/api/v1/bookings', bookingBody($trip, ['lead' => ['name' => 'X'], 'travelers' => []]))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['lead.email', 'travelers']);

    expect(Booking::query()->count())->toBe(0);
});

test('a booking can be viewed with its access token', function () {
    $booking = Booking::factory()->create(['reference' => 'BK-3001', 'access_token' => 'secret-token']);

    $this->getJson('/api/v1/bookings/BK-3001?token=secret-token')
        ->assertOk()
        ->assertJsonPath('data.reference', 'BK-3001')
        ->assertJsonMissingPath('data.access_token');

    $this->getJson('/api/v1/bookings/BK-3001')->assertNotFound();
    $this->getJson('/api/v1/bookings/BK-3001?token=wrong')->assertNotFound();
    expect($booking->exists)->toBeTrue();
});

test('a booking can be viewed by its owner but not by other customers', function () {
    $owner = User::factory()->create();
    Booking::factory()->for($owner)->create(['reference' => 'BK-3002']);

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/bookings/BK-3002')->assertNotFound();

    Sanctum::actingAs($owner);
    $this->getJson('/api/v1/bookings/BK-3002')->assertOk();
});

test('partner totals are only shown to the partner who booked', function () {
    $trip = buggyTrip();
    $partner = Partner::factory()->create(['default_commission' => 10]);
    Sanctum::actingAs($partner->user);

    $created = $this->postJson('/api/v1/bookings', bookingBody($trip))
        ->assertCreated()
        ->assertJsonPath('data.totals.partner_total', 76.5)
        ->assertJsonPath('data.totals.retail_total', 85);

    $token = $created->json('data.access_token');
    app('auth')->forgetGuards();

    $client = $this->withHeaders(['Authorization' => ''])->getJson("/api/v1/bookings/BK-2041?token={$token}")->assertOk();

    expect($client->json('data.totals'))->toBe(['retail_total' => 85, 'base_currency' => 'EUR', 'prices_hidden' => true])
        ->and($client->getContent())->not->toContain('partner_total');
});
