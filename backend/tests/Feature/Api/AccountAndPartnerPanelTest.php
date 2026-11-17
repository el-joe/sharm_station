<?php

use App\Enums\BookingStatus;
use App\Enums\CommissionCategory;
use App\Enums\PartnerStatus;
use App\Enums\PaymentStatus;
use App\Enums\ServiceType;
use App\Models\Booking;
use App\Models\ContactMessage;
use App\Models\Partner;
use App\Models\Trip;
use App\Models\User;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    seedCurrencies();
});

test('customers list only their own bookings', function () {
    $user = User::factory()->create();
    Booking::factory()->count(2)->create(['user_id' => $user->id, 'payment_due_at' => now()->addDays(2)]);
    Booking::factory()->create();

    $this->getJson('/api/v1/me/bookings')->assertUnauthorized();

    Sanctum::actingAs($user);
    $this->getJson('/api/v1/me/bookings?per_page=50')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('meta.per_page', 50)
        ->assertJsonPath('data.0.payment_due_at', now()->addDays(2)->toIso8601String());
});

test('customers update their profile and password', function () {
    $user = User::factory()->create(['password' => 'old-password-1']);
    Sanctum::actingAs($user);

    $this->patchJson('/api/v1/me/profile', ['name' => 'Giulia Bianchi', 'phone' => '+39 1', 'locale' => 'it', 'currency' => 'usd', 'newsletter' => true])
        ->assertOk()
        ->assertJsonPath('data.name', 'Giulia Bianchi')
        ->assertJsonPath('data.currency', 'USD')
        ->assertJsonPath('data.newsletter', true);

    $this->patchJson('/api/v1/me/profile', ['locale' => 'fr'])->assertUnprocessable()->assertJsonValidationErrors(['locale']);

    $this->putJson('/api/v1/me/password', ['current_password' => 'wrong', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
        ->assertUnprocessable()->assertJsonValidationErrors(['current_password']);
    $this->putJson('/api/v1/me/password', ['current_password' => 'old-password-1', 'password' => 'new-password-1', 'password_confirmation' => 'new-password-1'])
        ->assertNoContent();

    expect(Hash::check('new-password-1', $user->fresh()->password))->toBeTrue();
});

test('a new booking has a payment deadline', function () {
    $trip = buggyTrip();

    $response = $this->postJson('/api/v1/bookings', [
        'trip_slug' => $trip->slug, 'booking_type' => 'group', 'travel_date' => '2026-11-25', 'adults' => 1,
        'units' => [['unit_option_id' => $trip->unitOptions[0]->id, 'quantity' => 1]],
        'nationality_code' => 'IT',
        'lead' => ['name' => 'A B', 'email' => 'a@example.com'],
        'travelers' => [['first_name' => 'A', 'last_name' => 'B', 'type' => 'adult']],
    ])->assertCreated();

    expect($response->json('data.payment_due_at'))->toBe(now()->addHours(48)->toIso8601String());
});

test('the partner panel is for active partners only', function () {
    $pending = Partner::factory()->pending()->create();
    Sanctum::actingAs($pending->user);
    $this->getJson('/api/v1/partner/dashboard')->assertForbidden();

    Sanctum::actingAs(User::factory()->create());
    $this->getJson('/api/v1/partner/bookings')->assertForbidden();
});

test('partners see their dashboard, bookings, statements, price list and profile', function () {
    $partner = Partner::factory()->create(['company' => 'Viaggi Sole']);
    partnerOverride($partner, CommissionCategory::SingleDayTrips, 10);
    Sanctum::actingAs($partner->user);
    Trip::factory()->create(['title' => ['en' => 'Snorkelling'], 'base_price' => 50, 'from_price' => 50]);

    Booking::factory()->create(['partner_id' => $partner->id, 'retail_total' => 100, 'total' => 90, 'partner_total' => 90, 'commission_amount' => 10, 'deposit_amount' => 27]);
    Booking::factory()->create(['partner_id' => $partner->id, 'service_type' => ServiceType::Accommodation, 'retail_total' => 200, 'total' => 180, 'partner_total' => 180, 'commission_amount' => 20,
        'payment_status' => PaymentStatus::FullyPaid, 'status' => BookingStatus::FullyPaid, 'created_at' => '2026-09-10']);
    Booking::factory()->create();

    $this->getJson('/api/v1/partner/dashboard')
        ->assertOk()
        ->assertJsonPath('data.kpis.bookings_month', 1)
        ->assertJsonPath('data.kpis.sales_month', 100)
        ->assertJsonPath('data.kpis.commission_saved_month', 10)
        ->assertJsonPath('data.kpis.balance_due', 90)
        ->assertJsonPath('data.kpis.pending_proofs', 0)
        ->assertJsonPath('data.commissions.0.category', 'single_day_trips')
        ->assertJsonPath('data.commissions.0.percent', 10)
        ->assertJsonPath('data.commissions.0.source', 'partner_override')
        ->assertJsonPath('data.commissions.1.source', 'global_setting')
        ->assertJsonCount(2, 'data.recent_bookings');

    $this->getJson('/api/v1/partner/bookings?service=accommodation')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.totals.partner_total', 180);

    $this->getJson('/api/v1/partner/statements')
        ->assertOk()
        ->assertJsonCount(2, 'data')
        ->assertJsonPath('data.0.period', '2026-11')
        ->assertJsonPath('data.0.status', 'open')
        ->assertJsonPath('data.0.balance', 90)
        ->assertJsonPath('data.1.status', 'paid');

    $this->getJson('/api/v1/partner/price-list?service=single_day_trips')
        ->assertOk()
        ->assertJsonPath('data.0.title', 'Snorkelling')
        ->assertJsonPath('data.0.retail_price.amount_eur', 50)
        ->assertJsonPath('data.0.partner_price.amount_eur', 45)
        ->assertJsonPath('data.0.commission.source', 'partner_override');

    $this->patchJson('/api/v1/partner/profile', ['contact_name' => 'Marco', 'notify_statements' => false])
        ->assertOk()
        ->assertJsonPath('data.company', 'Viaggi Sole')
        ->assertJsonPath('data.contact_name', 'Marco')
        ->assertJsonPath('data.notify_statements', false)
        ->assertJsonPath('data.status', PartnerStatus::Active->value)
        ->assertJsonPath('data.commissions.0.percent', 10);

    $partner->update(['price_list_enabled' => false]);
    Sanctum::actingAs($partner->user->fresh());
    $this->getJson('/api/v1/partner/price-list')->assertForbidden();
});

test('the contact form stores the message and shows it in the admin', function () {
    $this->postJson('/api/v1/contact', ['name' => 'Giulia', 'email' => 'G@example.com', 'subject' => 'booking', 'booking_reference' => 'bk-2041', 'message' => 'Can I change the date of my trip?', 'locale' => 'it'])
        ->assertCreated()
        ->assertJsonPath('data.received', true);

    $this->postJson('/api/v1/contact', ['name' => 'X', 'email' => 'bad', 'subject' => 'spam', 'message' => 'short'])
        ->assertUnprocessable()->assertJsonValidationErrors(['email', 'subject', 'message']);

    $message = ContactMessage::query()->sole();
    expect($message->email)->toBe('g@example.com')->and($message->booking_reference)->toBe('BK-2041');

    $admin = staffUser();
    $this->withoutVite()->actingAs($admin)->get(route('admin.contact-messages.index'))->assertOk();
    $this->actingAs($admin)->getJson(route('admin.contact-messages.data', ['draw' => 1, 'start' => 0, 'length' => 10]))->assertOk()->assertJsonCount(1, 'data');
    $this->actingAs($admin)->get(route('admin.contact-messages.show', $message))->assertOk()->assertSee('Can I change the date');
    expect($message->fresh()->status)->toBe('read');
});
