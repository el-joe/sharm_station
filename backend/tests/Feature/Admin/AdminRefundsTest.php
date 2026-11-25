<?php

use App\Enums\BookingStatus;
use App\Enums\NotificationEvent;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Mail\NotificationMail;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    seedCurrencies();
    Mail::fake();
    $this->admin = staffUser();
    $this->booking = Booking::factory()->create(['status' => BookingStatus::Confirmed, 'payment_status' => PaymentStatus::DepositPaid, 'total' => 100, 'deposit_amount' => 30, 'cancellation_requested_at' => now(), 'cancellation_reason' => 'Change of plans']);
});

function paidDeposit(Booking $booking, ?PaymentMethod $method = null, ?string $transaction = null): Payment
{
    return Payment::query()->create(['booking_id' => $booking->id, 'payment_method_id' => $method?->id, 'type' => PaymentType::Deposit, 'status' => PaymentRecordStatus::Succeeded,
        'amount' => 30, 'currency' => 'EUR', 'exchange_rate' => 1, 'amount_in_currency' => 30, 'paid_at' => now(), 'transaction_id' => $transaction]);
}

test('approving a cancellation refunds an online deposit through its gateway and marks the booking refunded', function () {
    $card = PaymentMethod::factory()->online('fake')->create();
    $deposit = paidDeposit($this->booking, $card, 'tx_1');

    $this->actingAs($this->admin)->post(route('admin.bookings.cancellation.approve', $this->booking), ['refund_amount' => 30])->assertRedirect();

    $refund = Payment::query()->where('type', PaymentType::Refund)->sole();
    $booking = $this->booking->fresh();
    expect($refund->status)->toBe(PaymentRecordStatus::Succeeded)
        ->and($refund->parent_payment_id)->toBe($deposit->id)
        ->and($refund->transaction_id)->toStartWith('fake_re_')
        ->and($booking->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($booking->status)->toBe(BookingStatus::Refunded)
        ->and($booking->paidAmount())->toBe(0.0);

    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->event === NotificationEvent::CancellationApproved);
    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->event === NotificationEvent::RefundIssued);
});

test('a manual deposit gets a pending manual refund the admin marks as paid', function () {
    paidDeposit($this->booking);

    $this->actingAs($this->admin)->post(route('admin.bookings.cancellation.approve', $this->booking), ['refund_amount' => 15])->assertRedirect();

    $refund = Payment::query()->where('type', PaymentType::Refund)->sole();
    expect($refund->status)->toBe(PaymentRecordStatus::Pending)
        ->and($this->booking->fresh()->payment_status)->toBe(PaymentStatus::DepositPaid)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Cancelled);
    Mail::assertNotQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->event === NotificationEvent::RefundIssued);

    $this->actingAs($this->admin)->get(route('admin.bookings.show', $this->booking))->assertOk()->assertSee('Mark as paid');
    $this->actingAs($this->admin)->post(route('admin.bookings.refunds.paid', [$this->booking, $refund]))->assertRedirect();

    expect($refund->fresh()->status)->toBe(PaymentRecordStatus::Succeeded)
        ->and($this->booking->fresh()->payment_status)->toBe(PaymentStatus::PartiallyRefunded);
    Mail::assertQueued(NotificationMail::class, fn (NotificationMail $mail): bool => $mail->event === NotificationEvent::RefundIssued);

    $this->actingAs($this->admin)->post(route('admin.bookings.refunds.paid', [$this->booking, $refund]))->assertSessionHasErrors('refund');
});

test('the admin records partial and full refunds within what was received', function () {
    paidDeposit($this->booking);

    $this->actingAs($this->admin)->post(route('admin.bookings.refunds.store', $this->booking), ['amount' => 40, 'paid_at' => now()->toDateString()])
        ->assertSessionHasErrors('amount');
    $this->actingAs($this->admin)->post(route('admin.bookings.refunds.store', $this->booking), ['amount' => 10, 'paid_at' => now()->toDateString(), 'through_gateway' => 1])
        ->assertSessionHasErrors('amount');

    $this->actingAs($this->admin)->post(route('admin.bookings.refunds.store', $this->booking), ['amount' => 10, 'paid_at' => now()->toDateString(), 'note' => 'Goodwill'])->assertRedirect();
    expect($this->booking->fresh()->payment_status)->toBe(PaymentStatus::PartiallyRefunded)
        ->and($this->booking->fresh()->paidAmount())->toBe(20.0);

    $this->actingAs($this->admin)->post(route('admin.bookings.refunds.store', $this->booking), ['amount' => 20, 'paid_at' => now()->toDateString()])->assertRedirect();
    expect($this->booking->fresh()->payment_status)->toBe(PaymentStatus::Refunded)
        ->and($this->booking->fresh()->status)->toBe(BookingStatus::Confirmed);
});

test('a declined cancellation can be requested again', function () {
    $this->actingAs($this->admin)->post(route('admin.bookings.cancellation.decline', $this->booking), ['decline_reason' => 'Non-refundable'])->assertRedirect();

    $this->getJson("/api/v1/bookings/{$this->booking->reference}?token={$this->booking->access_token}")
        ->assertJsonPath('data.cancellation.status', 'declined')
        ->assertJsonPath('data.cancellation.can_request', true);

    $this->postJson("/api/v1/bookings/{$this->booking->reference}/cancellation-request?token={$this->booking->access_token}", ['reason' => 'Medical certificate attached'])
        ->assertCreated()
        ->assertJsonPath('data.cancellation.status', 'pending')
        ->assertJsonPath('data.cancellation.reason', 'Medical certificate attached')
        ->assertJsonPath('data.cancellation.can_request', false);

    expect($this->booking->fresh()->cancellation_decision)->toBeNull();
});

test('the admin sets the balance due date and downloads the voucher with or without prices', function () {
    $this->actingAs($this->admin)->patch(route('admin.bookings.balance-due', $this->booking), ['balance_due_on' => '2027-01-10'])->assertRedirect();
    expect($this->booking->fresh()->balance_due_on->toDateString())->toBe('2027-01-10');

    $this->actingAs($this->admin)->get(route('admin.bookings.voucher', $this->booking))->assertOk()->assertHeader('Content-Type', 'application/pdf');
    $this->actingAs($this->admin)->get(route('admin.bookings.voucher', [$this->booking, 'prices' => 0]))
        ->assertOk()->assertHeader('Content-Disposition', 'inline; filename="voucher-'.$this->booking->reference.'-no-prices.pdf"');
});
