<?php

use App\Enums\BookingStatus;
use App\Enums\CommissionCategory;
use App\Enums\PartnerStatus;
use App\Enums\PaymentStatus;
use App\Enums\ServiceType;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\GoogleReview;
use App\Models\Partner;
use App\Models\User;

beforeEach(function () {
    $this->withoutVite();
    $this->admin = staffUser();
});

function dataTableQuery(array $filters = []): array
{
    return ['draw' => 3, 'start' => 0, 'length' => 10, 'order' => [['column' => 0, 'dir' => 'desc']],
        'columns' => [['data' => 'reference', 'name' => 'reference', 'searchable' => 'false', 'orderable' => 'true']], ...$filters];
}

test('the bookings DataTables endpoint filters by search, status, payment status, partner and date', function () {
    $partner = Partner::factory()->create(['company' => 'Delta Tours']);
    Booking::factory()->create(['reference' => 'BK-3001', 'lead_name' => 'Giulia Rossi', 'lead_phone' => '+39 333 1111111', 'partner_id' => $partner->id,
        'payment_status' => PaymentStatus::DepositPaid, 'status' => BookingStatus::Confirmed, 'start_date' => '2026-12-12', 'total' => 1240, 'deposit_amount' => 372]);
    Booking::factory()->create(['reference' => 'BK-3002', 'lead_name' => 'Mark Taylor', 'service_type' => ServiceType::NileTrip, 'start_date' => '2027-02-01']);

    $all = $this->actingAs($this->admin)->getJson(route('admin.bookings.data', dataTableQuery()));
    $all->assertOk()->assertJsonPath('draw', 3)->assertJsonPath('recordsTotal', 2)->assertJsonPath('recordsFiltered', 2);

    $row = $this->actingAs($this->admin)->getJson(route('admin.bookings.data', dataTableQuery(['search' => ['value' => 'giulia']])))
        ->assertJsonPath('recordsFiltered', 1)->json('data.0');
    expect($row['reference'])->toContain('BK-3001')
        ->and($row['paid'])->toBe('€372.00')
        ->and($row['balance'])->toBe('€868.00')
        ->and($row['payment_status'])->toContain('Deposit paid');

    $byPhone = fn (array $filters) => $this->actingAs($this->admin)->getJson(route('admin.bookings.data', dataTableQuery($filters)))->json('recordsFiltered');
    expect($byPhone(['search' => ['value' => '3331111111']]))->toBe(1)
        ->and($byPhone(['payment_status' => 'deposit_paid']))->toBe(1)
        ->and($byPhone(['partner_id' => $partner->id]))->toBe(1)
        ->and($byPhone(['service_type' => 'nile_trip']))->toBe(1)
        ->and($byPhone(['date_from' => '2027-01-01']))->toBe(1)
        ->and($byPhone(['date_from' => '2026-12-01', 'date_to' => '2026-12-31']))->toBe(1);
});

test('the filtered bookings list exports to CSV', function () {
    Booking::factory()->create(['reference' => 'BK-4001', 'lead_name' => '=HYPERLINK("x")']);
    Booking::factory()->create(['reference' => 'BK-4002', 'status' => BookingStatus::Cancelled]);

    $response = $this->actingAs($this->admin)->get(route('admin.bookings.export', ['status' => 'awaiting_payment']));

    $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    $csv = $response->streamedContent();
    expect($csv)->toContain('Reference,Created,Service')
        ->toContain('BK-4001')
        ->toContain("'=HYPERLINK")
        ->not->toContain('BK-4002');
});

test('the booking detail shows price lines and changes status through BookingService with a log', function () {
    $booking = Booking::factory()->create(['price_lines' => [
        ['type' => 'unit', 'label' => 'Double buggy', 'quantity' => 2, 'unit_price' => 58, 'total' => 116, 'meta' => ['season' => 'High season']],
    ]]);

    $this->actingAs($this->admin)->get(route('admin.bookings.show', $booking))
        ->assertOk()->assertSee('Double buggy')->assertSee('Season: High season');

    $this->actingAs($this->admin)->patch(route('admin.bookings.status', $booking), ['status' => 'cancelled'])
        ->assertSessionHasErrors(['note' => 'Add a note explaining the cancellation or refund.']);

    $this->actingAs($this->admin)->patch(route('admin.bookings.status', $booking), ['status' => 'confirmed', 'note' => 'Deposit received by bank transfer'])
        ->assertRedirect(route('admin.bookings.show', $booking));

    expect($booking->fresh()->status)->toBe(BookingStatus::Confirmed);
    $this->assertDatabaseHas('booking_status_logs', [
        'booking_id' => $booking->id, 'from_status' => 'awaiting_payment', 'to_status' => 'confirmed',
        'user_id' => $this->admin->id, 'note' => 'Deposit received by bank transfer',
    ]);
});

test('the commission override matrix saves and clears partner overrides', function () {
    $alpha = Partner::factory()->create();
    $beta = Partner::factory()->pending()->create();
    $alpha->categoryCommissions()->create(['category' => CommissionCategory::Packages, 'commission_percent' => 5]);

    $this->actingAs($this->admin)->get(route('admin.partners.commissions.edit'))->assertOk()->assertSee($alpha->company)->assertSee($beta->company)->assertDontSee('Default %');

    $this->actingAs($this->admin)->put(route('admin.partners.commissions.update'), ['partners' => [
        $alpha->id => ['categories' => ['single_day_trips' => '15', 'nile_trips' => '', 'packages' => '', 'transfers' => '8']],
        $beta->id => ['categories' => ['multi_day_trips' => '20']],
    ]])->assertRedirect(route('admin.partners.commissions.edit'))->assertSessionHas('success', 'Commissions saved for 2 partners.');

    $alpha->refresh()->load('categoryCommissions');
    $beta->refresh()->load('categoryCommissions');
    expect($alpha->commissionFor(CommissionCategory::SingleDayTrips))->toBe(15.0)
        ->and($alpha->commissionFor(CommissionCategory::Transfers))->toBe(8.0)
        ->and($alpha->commissionFor(CommissionCategory::Packages))->toBeNull()
        ->and($beta->commissionFor(CommissionCategory::MultiDayTrips))->toBe(20.0);
});

test('the commission matrix rejects percentages above 100', function () {
    $partner = Partner::factory()->create();

    $this->actingAs($this->admin)->put(route('admin.partners.commissions.update'), ['partners' => [
        $partner->id => ['categories' => ['packages' => '120']],
    ]])->assertSessionHasErrors(["partners.{$partner->id}.categories.packages"]);
});

test('partners are listed and can be approved and suspended', function () {
    $pending = Partner::factory()->pending()->create(['user_id' => User::factory()->create()->id, 'company' => 'Cairo Direct']);

    $this->actingAs($this->admin)->getJson(route('admin.partners.data', ['status' => 'pending', 'draw' => 1, 'start' => 0, 'length' => 10]))
        ->assertOk()->assertJsonPath('recordsTotal', 1);

    $this->actingAs($this->admin)->post(route('admin.partners.approve', $pending))->assertSessionHas('success');
    expect($pending->fresh()->status)->toBe(PartnerStatus::Active)
        ->and($pending->user->fresh()->role)->toBe(UserRole::Partner);

    $this->actingAs($this->admin)->post(route('admin.partners.suspend', $pending))->assertSessionHas('success');
    expect($pending->fresh()->status)->toBe(PartnerStatus::Suspended);
});

test('a cached Google review can be hidden and shown again with a JSON toggle', function () {
    $review = GoogleReview::factory()->create(['place_id' => 'ChIJ-business']);

    $this->actingAs($this->admin)->patchJson(route('admin.google-reviews.update', $review), ['hidden' => true])
        ->assertOk()->assertJson(['hidden' => true, 'message' => 'Review hidden from the site.']);
    expect($review->fresh()->is_hidden)->toBeTrue();

    $this->actingAs(staffUser(UserRole::Editor))->patchJson(route('admin.google-reviews.update', $review), ['hidden' => false])
        ->assertOk()->assertJson(['hidden' => false]);
    expect($review->fresh()->is_hidden)->toBeFalse();

    $this->actingAs($this->admin)->patchJson(route('admin.google-reviews.update', $review), [])->assertUnprocessable();
});

test('the Google reviews page shows cached reviews and saves the business Place ID', function () {
    GoogleReview::factory()->create(['place_id' => 'ChIJ-business', 'author_name' => 'Elena B.']);

    $this->actingAs($this->admin)->put(route('admin.google-reviews.place'), ['place_id' => 'ChIJ-business'])
        ->assertRedirect(route('admin.google-reviews.index'));

    $this->assertDatabaseHas('settings', ['key' => 'google_reviews.place_id']);
    $this->actingAs($this->admin)->get(route('admin.google-reviews.index'))->assertOk()->assertSee('Elena B.');
});
