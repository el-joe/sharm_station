<?php

use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\Coupon;
use App\Models\Partner;
use App\Models\Trip;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    seedCurrencies();
});

/**
 * Buggy trip booking for 3 adults: Single 35 + Double 50 = EUR 85.
 */
function couponBookingBody(Trip $trip, array $overrides = []): array
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
        'nationality_code' => 'IT',
        'lead' => ['name' => 'Giulia Rossi', 'email' => 'giulia@example.com'],
        'travelers' => [
            ['first_name' => 'Giulia', 'last_name' => 'Rossi', 'type' => 'adult'],
            ['first_name' => 'Paolo', 'last_name' => 'Rossi', 'type' => 'adult'],
            ['first_name' => 'Anna', 'last_name' => 'Rossi', 'type' => 'adult'],
        ],
        ...$overrides,
    ];
}

test('a valid code returns the contract shape with the discount in EUR', function () {
    Coupon::factory()->create(['code' => 'WELCOME10', 'value' => 10]);

    $this->postJson('/api/v1/coupons/validate', ['code' => 'welcome10', 'service_type' => 'trip', 'total_eur' => 85])
        ->assertOk()
        ->assertExactJson([
            'valid' => true, 'code' => 'WELCOME10', 'discount_type' => 'percent', 'value' => 10, 'discount_eur' => 8.5, 'message' => 'Code applied: −€8.50.',
        ]);
});

test('invalid codes explain why', function (array $coupon, array $request, string $message) {
    Coupon::factory()->create(['code' => 'SUMMER', ...$coupon]);

    $this->postJson('/api/v1/coupons/validate', ['code' => 'SUMMER', 'service_type' => 'trip', 'total_eur' => 85, ...$request])
        ->assertOk()
        ->assertJsonPath('valid', false)
        ->assertJsonPath('discount_eur', 0)
        ->assertJsonPath('message', $message);
})->with([
    'inactive' => [['is_active' => false], [], 'This code is no longer active.'],
    'expired' => [['valid_to' => '2026-11-01'], [], 'This code has expired.'],
    'not started' => [['valid_from' => '2026-12-01'], [], 'This code is valid from 1 Dec 2026.'],
    'other service' => [['services' => ['accommodation']], [], 'This code does not apply to trip bookings.'],
    'minimum total' => [['minimum_total' => 100], [], 'This code needs a minimum total of €100.00.'],
    'unknown' => [[], ['code' => 'NOPE'], 'This code does not exist.'],
]);

test('usage limits count redemptions in total and per customer email', function () {
    $coupon = Coupon::factory()->create(['code' => 'ONCE', 'usage_limit_per_customer' => 1, 'usage_limit' => 2]);
    $coupon->redemptions()->create(['booking_id' => Booking::factory()->create()->id, 'email' => 'giulia@example.com', 'discount_amount' => 5]);

    $check = fn (string $email) => $this->postJson('/api/v1/coupons/validate', ['code' => 'ONCE', 'service_type' => 'trip', 'total_eur' => 85, 'email' => $email])->json();

    expect($check('giulia@example.com'))->toMatchArray(['valid' => false, 'message' => 'You have already used this code.'])
        ->and($check('mark@example.com')['valid'])->toBeTrue();

    $coupon->redemptions()->create(['booking_id' => Booking::factory()->create()->id, 'email' => 'other@example.com', 'discount_amount' => 5]);
    expect($check('mark@example.com'))->toMatchArray(['valid' => false, 'message' => 'This code has reached its usage limit.']);
});

test('the booking applies the coupon on the server as a discount line and records the redemption', function () {
    $trip = buggyTrip();
    $coupon = Coupon::factory()->fixed(20)->create(['code' => 'NILE20', 'services' => [ServiceType::Trip->value]]);

    $response = $this->postJson('/api/v1/bookings', couponBookingBody($trip, ['coupon_code' => 'nile20']))
        ->assertCreated()
        ->assertJsonPath('data.coupon_code', 'NILE20')
        ->assertJsonPath('data.totals.retail_total', 85)
        ->assertJsonPath('data.totals.total', 65)
        ->assertJsonPath('data.totals.discount_amount', 20)
        ->assertJsonPath('data.totals.deposit_amount', 19.5);

    expect(collect($response->json('data.lines'))->last())->toMatchArray(['type' => 'discount', 'label' => 'Coupon NILE20', 'total' => -20]);

    $booking = Booking::query()->where('reference', $response->json('data.reference'))->firstOrFail();
    expect($booking->coupon_id)->toBe($coupon->id)
        ->and((float) $booking->discount_amount)->toBe(20.0)
        ->and((float) $booking->balance_amount)->toBe(45.5);
    $this->assertDatabaseHas('coupon_redemptions', ['coupon_id' => $coupon->id, 'booking_id' => $booking->id, 'email' => 'giulia@example.com', 'discount_amount' => 20]);
});

test('a booking with an unusable code is rejected and nothing is stored', function () {
    $trip = buggyTrip();
    Coupon::factory()->create(['code' => 'OLD', 'valid_to' => '2026-01-01']);

    $this->postJson('/api/v1/bookings', couponBookingBody($trip, ['coupon_code' => 'OLD']))
        ->assertUnprocessable()
        ->assertJsonValidationErrors(['coupon_code' => 'This code has expired.']);

    expect(Booking::query()->count())->toBe(0);
});

test('partner bookings only accept codes that allow partners, for partners with coupon permission', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    snorkellingTrip(['base_price' => 45, 'base_child_price' => 30]);
    $partner = Partner::factory()->create(['coupons_allowed' => false]);
    Coupon::factory()->create(['code' => 'AGENCY5', 'value' => 5, 'partners_allowed' => true]);
    Coupon::factory()->create(['code' => 'PUBLIC10', 'value' => 10]);
    Sanctum::actingAs($partner->user);

    $payload = fn (string $code) => [
        'product_type' => 'trip', 'slug' => 'ras-mohammed-snorkelling', 'nationality_code' => 'EG', 'booking_type' => 'group',
        'travel_date' => '2026-11-25', 'adults' => 1, 'coupon_code' => $code,
        'lead' => ['name' => 'Ahmed Hassan', 'email' => 'ahmed@example.com'],
        'travelers' => [['first_name' => 'Ahmed', 'last_name' => 'Hassan', 'type' => 'adult']],
    ];

    $this->postJson('/api/v1/partner/bookings', $payload('AGENCY5'))
        ->assertUnprocessable()->assertJsonValidationErrors(['coupon_code' => 'Coupon codes cannot be used on partner bookings.']);

    $partner->update(['coupons_allowed' => true]);
    Sanctum::actingAs($partner->user->fresh());

    $this->postJson('/api/v1/partner/bookings', $payload('PUBLIC10'))->assertUnprocessable();
    $this->postJson('/api/v1/partner/bookings', $payload('AGENCY5'))
        ->assertCreated()
        ->assertJsonPath('data.totals.partner_total', 36.34)
        ->assertJsonPath('data.totals.total', 36.34)
        ->assertJsonPath('data.coupon_code', 'AGENCY5');
});
