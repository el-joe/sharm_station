<?php

use App\DTOs\Payment\RefundResultData;
use App\Enums\BookingStatus;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\TripType;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Services\Payments\PaymentGateway;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentGatewayManager;
use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;

/**
 * Gateway double that records the transaction level at the time of the call and can refuse refunds.
 */
function recordingGateway(bool $fails): PaymentGateway
{
    $gateway = Mockery::mock(PaymentGateway::class);
    $gateway->shouldReceive('isConfigured')->andReturnTrue();
    $gateway->shouldReceive('supportsRefunds')->andReturnTrue();
    $gateway->shouldReceive('refund')->once()->andReturnUsing(function () use ($fails): RefundResultData {
        test()->gatewayTransactionLevel = DB::transactionLevel();

        if ($fails) {
            throw new PaymentGatewayException('Card network unavailable');
        }

        return new RefundResultData('re_after_commit');
    });

    return $gateway;
}

test('confirmed and fully paid bookings are completed the day after their service ended', function () {
    $this->travelTo(CarbonImmutable::parse('2026-12-10 00:30'));
    $stay = Booking::factory()->create(['status' => BookingStatus::Confirmed, 'check_in' => '2026-12-05', 'check_out' => '2026-12-09', 'end_date' => '2026-12-09']);
    $endsToday = Booking::factory()->create(['status' => BookingStatus::FullyPaid, 'start_date' => '2026-12-10', 'end_date' => '2026-12-10']);
    $unpaid = Booking::factory()->create(['status' => BookingStatus::AwaitingPayment, 'start_date' => '2026-12-01', 'end_date' => '2026-12-01']);
    $legacy = Booking::factory()->create(['status' => BookingStatus::FullyPaid, 'start_date' => '2026-12-08', 'end_date' => null, 'check_out' => null]);

    $this->artisan('bookings:mark-completed')->expectsOutput('Bookings completed: 2')->assertSuccessful();

    expect($stay->fresh()->status)->toBe(BookingStatus::Completed)
        ->and($legacy->fresh()->status)->toBe(BookingStatus::Completed)
        ->and($endsToday->fresh()->status)->toBe(BookingStatus::FullyPaid)
        ->and($unpaid->fresh()->status)->toBe(BookingStatus::AwaitingPayment)
        ->and($stay->statusLogs()->latest('id')->first()->note)->toBe('Service ended on 2026-12-09');

    $this->artisan('bookings:mark-completed')->expectsOutput('Bookings completed: 0');
});

test('a new trip booking stores the last day of the trip as its end date', function () {
    $this->travelTo(CarbonImmutable::parse('2026-11-13 10:00'));
    seedCurrencies();
    $trip = snorkellingTrip(['type' => TripType::MultiDay, 'days' => 3, 'nights' => 2]);

    $this->postJson('/api/v1/bookings', [
        'trip_slug' => $trip->slug, 'booking_type' => 'group', 'travel_date' => '2026-11-25', 'adults' => 1, 'nationality_code' => 'IT',
        'lead' => ['name' => 'Anna Rossi', 'email' => 'anna@example.com'],
        'travelers' => [['first_name' => 'Anna', 'last_name' => 'Rossi', 'type' => 'adult']],
    ])->assertCreated()->assertJsonPath('data.end_date', '2026-11-27');
});

describe('refunds after commit', function () {
    beforeEach(function () {
        seedCurrencies();
        Mail::fake();
        $this->admin = staffUser();
        $this->booking = Booking::factory()->create(['status' => BookingStatus::Confirmed, 'payment_status' => PaymentStatus::DepositPaid, 'total' => 100, 'deposit_amount' => 30, 'cancellation_requested_at' => now(), 'cancellation_reason' => 'Change of plans']);
        $card = PaymentMethod::factory()->online('fake')->create();
        $this->deposit = Payment::query()->create(['booking_id' => $this->booking->id, 'payment_method_id' => $card->id, 'type' => PaymentType::Deposit, 'status' => PaymentRecordStatus::Succeeded,
            'amount' => 30, 'currency' => 'EUR', 'exchange_rate' => 1, 'amount_in_currency' => 30, 'paid_at' => now(), 'transaction_id' => 'tx_1']);
    });

    test('the gateway is called after the cancellation transaction has committed', function () {
        $baseline = DB::transactionLevel();
        $this->partialMock(PaymentGatewayManager::class, fn ($mock) => $mock->shouldReceive('driver')->andReturn(recordingGateway(false)));

        $this->actingAs($this->admin)->post(route('admin.bookings.cancellation.approve', $this->booking), ['refund_amount' => 30])->assertRedirect();

        $refund = Payment::query()->where('type', PaymentType::Refund)->sole();
        expect($this->gatewayTransactionLevel)->toBe($baseline)
            ->and($refund->status)->toBe(PaymentRecordStatus::Succeeded)
            ->and($refund->transaction_id)->toBe('re_after_commit')
            ->and($this->booking->fresh()->status)->toBe(BookingStatus::Refunded);
    });

    test('a gateway failure keeps the cancellation and leaves a pending manual refund', function () {
        $this->partialMock(PaymentGatewayManager::class, fn ($mock) => $mock->shouldReceive('driver')->andReturn(recordingGateway(true)));

        $this->actingAs($this->admin)->post(route('admin.bookings.cancellation.approve', $this->booking), ['refund_amount' => 30])->assertRedirect();

        $refund = Payment::query()->where('type', PaymentType::Refund)->sole();
        expect($this->booking->fresh()->status)->toBe(BookingStatus::Cancelled)
            ->and($refund->status)->toBe(PaymentRecordStatus::Pending)
            ->and($refund->parent_payment_id)->toBeNull()
            ->and($refund->note)->toContain('Manual refund to pay (the gateway refused the online refund')
            ->and($refund->meta['gateway_refund'])->toBe('failed');
    });
});
