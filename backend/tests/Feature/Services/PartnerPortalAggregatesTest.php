<?php

use App\Enums\BookingStatus;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Models\Booking;
use App\Models\Partner;
use App\Models\Payment;
use App\Services\PartnerPortalService;
use Carbon\CarbonImmutable;
use Laravel\Sanctum\Sanctum;

beforeEach(function () {
    seedCurrencies();
    $this->travelTo(CarbonImmutable::parse('2026-11-20 10:00'));
    $this->partner = Partner::factory()->create();

    $make = fn (string $created, array $attributes) => Booking::factory()->create(['partner_id' => $this->partner->id, 'created_at' => $created, ...$attributes]);
    $pay = fn (Booking $booking, PaymentType $type, float $amount, PaymentRecordStatus $status = PaymentRecordStatus::Succeeded) => Payment::query()->create([
        'booking_id' => $booking->id, 'type' => $type, 'status' => $status, 'amount' => $amount, 'currency' => 'EUR', 'exchange_rate' => 1, 'amount_in_currency' => $amount,
    ]);

    $a = $make('2026-11-03 09:00', ['retail_total' => 120.5, 'commission_amount' => 18.07, 'partner_total' => 102.43, 'total' => 102.43, 'deposit_amount' => 30.73, 'status' => BookingStatus::Confirmed, 'payment_status' => PaymentStatus::DepositPaid]);
    $pay($a, PaymentType::Deposit, 30.73);
    $pay($a, PaymentType::Balance, 10, PaymentRecordStatus::Failed);
    $make('2026-11-10 12:00', ['retail_total' => 200, 'commission_amount' => 30, 'partner_total' => 170, 'total' => 170, 'deposit_amount' => 51, 'status' => BookingStatus::FullyPaid, 'payment_status' => PaymentStatus::FullyPaid]);
    $c = $make('2026-10-28 18:00', ['retail_total' => 90, 'commission_amount' => 13.5, 'partner_total' => 76.5, 'total' => 76.5, 'deposit_amount' => 22.95, 'status' => BookingStatus::Confirmed, 'payment_status' => PaymentStatus::PartiallyRefunded]);
    $pay($c, PaymentType::Deposit, 22.95);
    $pay($c, PaymentType::Refund, 5.5);
    $make('2026-10-02 08:00', ['retail_total' => 60, 'commission_amount' => 9, 'partner_total' => null, 'total' => 60, 'deposit_amount' => 18, 'status' => BookingStatus::AwaitingPayment]);
    $make('2026-11-11 08:00', ['retail_total' => 999, 'commission_amount' => 99, 'partner_total' => 900, 'total' => 900, 'status' => BookingStatus::Cancelled, 'payment_status' => PaymentStatus::DepositPaid]);
    Booking::factory()->create(['retail_total' => 500, 'total' => 500, 'created_at' => '2026-11-05 10:00']);
});

/**
 * The previous in-memory computation (per-booking paidAmount/dueAmount), used as the reference.
 */
function collectionDashboardKpis(Partner $partner): array
{
    $active = Booking::query()->where('partner_id', $partner->id)->whereNotIn('status', BookingStatus::voided())->with('payments')->get();
    $month = $active->filter(fn (Booking $booking): bool => $booking->created_at->gte(now()->startOfMonth()));

    return [
        'bookings_month' => $month->count(),
        'sales_month' => round((float) $month->sum(fn (Booking $b): float => (float) $b->retail_total), 2),
        'commission_saved_month' => round((float) $month->sum(fn (Booking $b): float => (float) $b->commission_amount), 2),
        'balance_due' => round((float) $active->sum(fn (Booking $b): float => $b->dueAmount()), 2),
        'partner_sales_month' => round((float) $month->sum(fn (Booking $b): float => (float) ($b->partner_total ?? $b->total)), 2),
    ];
}

test('dashboard KPIs aggregated in SQL equal the per-booking computation', function () {
    $kpis = app(PartnerPortalService::class)->dashboard($this->partner)['kpis'];

    expect(collect($kpis)->only(['bookings_month', 'sales_month', 'commission_saved_month', 'balance_due', 'partner_sales_month'])->all())
        ->toEqual(collectionDashboardKpis($this->partner))
        ->and($kpis['commission_earned_month'])->toEqual($kpis['commission_saved_month']);
});

test('monthly statements aggregated in SQL keep the response shape and the per-booking totals', function () {
    $statements = app(PartnerPortalService::class)->statements($this->partner);

    expect(array_column($statements, 'period'))->toBe(['2026-11', '2026-10']);

    foreach ($statements as $summary) {
        $bookings = Booking::query()->where('partner_id', $this->partner->id)->whereNotIn('status', BookingStatus::voided())->with('payments')->get()
            ->filter(fn (Booking $b): bool => $b->created_at->format('Y-m') === $summary['period']);
        $total = round((float) $bookings->sum(fn (Booking $b): float => (float) ($b->partner_total ?? $b->total)), 2);
        $paid = round((float) $bookings->sum(fn (Booking $b): float => $b->paidAmount()), 2);

        expect(array_keys($summary))->toBe(['id', 'period', 'period_start', 'period_end', 'bookings_count', 'retail_total', 'commission_amount', 'partner_total', 'paid', 'balance', 'status', 'pdf_url', 'excel_url'])
            ->and($summary['bookings_count'])->toBe($bookings->count())
            ->and($summary['partner_total'])->toEqual($total)
            ->and($summary['paid'])->toEqual($paid)
            ->and($summary['balance'])->toEqual(round(max(0, $total - $paid), 2));
    }

    expect(app(PartnerPortalService::class)->statement($this->partner, '2026-10')['summary'])->toEqual($statements[1]);
});

test('exporting a month without bookings answers 404 JSON', function () {
    Sanctum::actingAs($this->partner->user);

    $this->getJson('/api/v1/partner/statements/2026-09/export?format=pdf')->assertNotFound()->assertJsonPath('message', 'No bookings in this period.');
    $this->get('/api/v1/partner/statements/2026-10/export?format=xlsx')->assertOk();
});
