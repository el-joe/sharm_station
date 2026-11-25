<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\Partner;
use App\Models\User;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    seedCurrencies();
    $this->booking = Booking::factory()->create(['reference' => 'BK-7001', 'access_token' => 'voucher-token', 'status' => BookingStatus::Confirmed, 'payment_status' => PaymentStatus::DepositPaid, 'locale' => 'it']);
});

test('the voucher PDF is downloaded with the booking token, with or without prices', function () {
    $this->get('/api/v1/bookings/BK-7001/voucher.pdf?token=voucher-token')
        ->assertOk()
        ->assertHeader('Content-Type', 'application/pdf')
        ->assertHeader('Content-Disposition', 'attachment; filename="voucher-BK-7001.pdf"');

    $response = $this->get('/api/v1/bookings/BK-7001/voucher.pdf?token=voucher-token&prices=0&locale=en')->assertOk();
    expect($response->headers->get('Content-Disposition'))->toContain('no-prices')
        ->and(substr($response->getContent(), 0, 4))->toBe('%PDF');
});

test('the voucher needs access and a paid deposit', function () {
    $this->getJson('/api/v1/bookings/BK-7001/voucher.pdf')->assertNotFound();
    $this->getJson('/api/v1/bookings/BK-7001/voucher.pdf?token=wrong')->assertNotFound();
    $this->getJson('/api/v1/bookings/BK-7001/voucher.pdf?token=voucher-token&prices=2')->assertUnprocessable();

    $this->booking->update(['status' => BookingStatus::AwaitingPayment]);
    $this->getJson('/api/v1/bookings/BK-7001/voucher.pdf?token=voucher-token')->assertStatus(409);
});

test('the partner gets prices, the end client of a partner booking never does', function () {
    $partner = Partner::factory()->create();
    $this->booking->update(['partner_id' => $partner->id]);

    $this->get('/api/v1/bookings/BK-7001/voucher.pdf?token=voucher-token&prices=1')
        ->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="voucher-BK-7001-no-prices.pdf"');

    Sanctum::actingAs($partner->user);
    $this->get('/api/v1/bookings/BK-7001/voucher.pdf?prices=1')
        ->assertOk()->assertHeader('Content-Disposition', 'attachment; filename="voucher-BK-7001.pdf"');

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/bookings/BK-7001/voucher.pdf')->assertNotFound();
});
