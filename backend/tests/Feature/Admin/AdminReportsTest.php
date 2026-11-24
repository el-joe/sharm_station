<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\ServiceType;
use App\Enums\UserRole;
use App\Models\Booking;
use App\Models\Partner;
use App\Models\Payment;
use App\Models\Trip;
use Carbon\CarbonImmutable;

beforeEach(function () {
    $this->withoutVite();
    $this->travelTo(CarbonImmutable::parse('2026-11-24 10:00'));
    $this->admin = staffUser();
    $partner = Partner::factory()->create(['company' => 'Delta Tours']);
    $trip = Trip::factory()->create(['title' => ['en' => 'Desert safari']]);

    $direct = Booking::factory()->create(['bookable_id' => $trip->id, 'total' => 200, 'retail_total' => 200, 'deposit_amount' => 60, 'payment_status' => PaymentStatus::DepositPaid, 'status' => BookingStatus::Confirmed, 'nationality_group_id' => nationalityGroup('europe')->id]);
    Payment::query()->create(['booking_id' => $direct->id, 'type' => PaymentType::Deposit, 'status' => PaymentRecordStatus::Succeeded, 'amount' => 60, 'currency' => 'EUR', 'exchange_rate' => 1, 'amount_in_currency' => 60, 'paid_at' => now()]);
    Booking::factory()->create(['bookable_id' => $trip->id, 'partner_id' => $partner->id, 'total' => 90, 'retail_total' => 100, 'partner_total' => 90, 'commission_amount' => 10, 'created_at' => '2026-10-05']);
    Booking::factory()->create(['service_type' => ServiceType::Accommodation, 'total' => 500, 'status' => BookingStatus::Cancelled]);
    Booking::factory()->create(['total' => 70, 'created_at' => '2025-01-10']);
});

test('KPIs and tables are aggregated for the period, without cancelled bookings', function () {
    $response = $this->actingAs($this->admin)->get(route('admin.reports.index', ['date_from' => '2026-01-01', 'date_to' => '2026-11-30']))->assertOk();

    expect($response->viewData('kpis'))->toMatchArray([
        'bookings' => 2, 'revenue' => 290.0, 'deposits_collected' => 60.0, 'balance_due' => 230.0, 'partner_sales' => 90.0, 'commission_granted' => 10.0,
    ]);

    $tables = $response->viewData('tables');
    expect($tables['revenue']['rows'])->toBe([['2026-10', 'Trip', 1, 90.0], ['2026-11', 'Trip', 1, 200.0]])
        ->and($tables['best-sellers']['rows'][0])->toBe(['Desert safari', 'Trip', 2, 4, 290.0])
        ->and($tables['partners']['rows'])->toBe([['Delta Tours', 1, 100.0, 90.0, 10.0]])
        ->and($tables['payments']['rows'])->toBe([['Manual / cash', 'Deposit', 1, 60.0]])
        ->and(collect($tables['nationalities']['rows'])->firstWhere(0, 'Europe'))->toBe(['Europe', 1, 200.0]);

    $response->assertSee('Desert safari')->assertSee('Delta Tours');

    $filtered = $this->actingAs($this->admin)->get(route('admin.reports.index', ['date_from' => '2026-01-01', 'service_type' => 'accommodation']));
    expect($filtered->viewData('kpis')['bookings'])->toBe(0);
});

test('every report table exports to CSV', function () {
    foreach (['revenue', 'best-sellers', 'partners', 'nationalities', 'payments'] as $table) {
        $response = $this->actingAs($this->admin)->get(route('admin.reports.export', ['table' => $table, 'date_from' => '2026-01-01']));
        $response->assertOk()->assertHeader('content-type', 'text/csv; charset=UTF-8');
    }

    expect($this->actingAs($this->admin)->get(route('admin.reports.export', ['table' => 'partners', 'date_from' => '2026-01-01']))->streamedContent())
        ->toContain('Partner,Bookings')->toContain('"Delta Tours",1,100,90,10');
    $this->actingAs($this->admin)->get(route('admin.reports.export', 'unknown'))->assertNotFound();
});

test('editors cannot see reports', function () {
    $this->actingAs(staffUser(UserRole::Editor))->get(route('admin.reports.index'))->assertForbidden();
});
