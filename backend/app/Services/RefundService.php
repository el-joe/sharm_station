<?php

namespace App\Services;

use App\DTOs\Payment\RecordRefundData;
use App\Enums\ActivityAction;
use App\Enums\BookingStatus;
use App\Enums\NotificationEvent;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Exceptions\DomainValidationException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Notifications\NotificationContextFactory;
use App\Services\Notifications\NotificationService;
use App\Services\Payments\PaymentGatewayException;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Number;

/**
 * Refunds (SRS 10.2, 12.4). A refund is a Payment of type "refund" linked to the payment it pays back:
 * sent through the gateway of an online payment when it supports refunds (succeeded), otherwise a pending
 * manual refund the admin marks as paid. Succeeded refunds lower the paid amount and set the payment status
 * to "partially refunded" or "refunded" (a cancelled booking refunded in full becomes "Refunded").
 */
class RefundService
{
    public function __construct(
        private readonly PaymentRepositoryInterface $payments,
        private readonly BookingRepositoryInterface $bookings,
        private readonly PaymentGatewayManager $gateways,
        private readonly BookingService $bookingService,
        private readonly NotificationService $notifications,
        private readonly NotificationContextFactory $context,
        private readonly ActivityLogger $activity,
    ) {}

    /**
     * Money received (deposit and balance payments that succeeded), EUR.
     */
    public function received(Booking $booking): float
    {
        return round((float) $this->paymentsOf($booking)
            ->filter(fn (Payment $payment): bool => $payment->type !== PaymentType::Refund && $payment->status === PaymentRecordStatus::Succeeded)
            ->sum(fn (Payment $payment): float => (float) $payment->amount), 2);
    }

    /**
     * Refunds already paid (and, with $includePending, still to pay), EUR.
     */
    public function refunded(Booking $booking, bool $includePending = false): float
    {
        $statuses = $includePending ? [PaymentRecordStatus::Succeeded, PaymentRecordStatus::Pending] : [PaymentRecordStatus::Succeeded];

        return round((float) $this->paymentsOf($booking)
            ->filter(fn (Payment $payment): bool => $payment->type === PaymentType::Refund && in_array($payment->status, $statuses, true))
            ->sum(fn (Payment $payment): float => (float) $payment->amount), 2);
    }

    /**
     * What can still be refunded: received minus refunds paid or pending.
     */
    public function refundable(Booking $booking): float
    {
        return max(0.0, round($this->received($booking) - $this->refunded($booking, true), 2));
    }

    /**
     * Amount that can go back through gateways (online payments whose gateway supports refunds), EUR.
     */
    public function refundableOnline(Booking $booking): float
    {
        return round((float) $this->onlinePayments($booking)->sum(fn (Payment $payment): float => $this->remainingOn($booking, $payment)), 2);
    }

    /**
     * Refund of an approved cancellation: through the gateways first, the rest as a pending manual refund.
     * Must be called after the cancellation transaction has committed: gateway calls never run inside a DB transaction.
     *
     * @return list<Payment>
     */
    public function issueForCancellation(Booking $booking, float $amount, User $actor): array
    {
        $amount = min(round($amount, 2), $this->refundable($booking));

        if ($amount <= 0) {
            return [];
        }

        return $this->refund($booking, $amount, $actor, 'Cancellation refund', 'Manual refund to pay (cancellation)');
    }

    /**
     * Admin "Record refund": full or partial, paid outside the site or sent back through the gateway.
     *
     * @return list<Payment>
     *
     * @throws DomainValidationException
     */
    public function record(Booking $booking, RecordRefundData $data, User $actor): array
    {
        $refundable = $this->refundable($booking);

        if ($data->amount <= 0 || $data->amount > round($refundable + 0.001, 2)) {
            throw DomainValidationException::withErrors(['amount' => ['The refund must be between €0.01 and the refundable amount ('.Number::currency($refundable, 'EUR', 'en').').']]);
        }

        if ($data->throughGateway && $data->amount > round($this->refundableOnline($booking) + 0.001, 2)) {
            throw DomainValidationException::withErrors(['amount' => ['Only '.Number::currency($this->refundableOnline($booking), 'EUR', 'en').' can be refunded through the online gateway.']]);
        }

        if ($data->throughGateway) {
            return $this->refund($booking, $data->amount, $actor, $data->note ?? 'Refund', 'Manual refund to pay (the gateway refused the online refund)');
        }

        return DB::transaction(function () use ($booking, $data, $actor): array {
            $refunds = [$this->createRefund($booking, null, $data->amount, PaymentRecordStatus::Succeeded, $actor, [
                'payment_method_id' => $data->paymentMethodId,
                'paid_at' => $data->paidAt,
                'note' => $data->note ?? 'Refund recorded by the admin',
                'transaction_id' => $data->transactionId,
            ])];

            $this->afterRefund($booking, $refunds, $actor);

            return $refunds;
        });
    }

    /**
     * Gateway-first refund in three steps so no gateway call happens inside a DB transaction:
     * 1. one transaction records every planned online refund as "pending" (and the manual rest);
     * 2. the gateways are called after the commit;
     * 3. each outcome is recorded: succeeded, or turned into a pending manual refund when the gateway refuses.
     *
     * @return list<Payment>
     */
    private function refund(Booking $booking, float $amount, User $actor, string $note, string $manualNote): array
    {
        $planned = DB::transaction(function () use ($booking, $amount, $actor, $note, $manualNote): array {
            $planned = [];
            $remaining = round($amount, 2);

            foreach ($this->onlinePayments($booking) as $original) {
                $part = min($remaining, $this->remainingOn($booking, $original));

                if ($remaining <= 0 || $part <= 0) {
                    continue;
                }

                $planned[] = $this->createRefund($booking, $original, $part, PaymentRecordStatus::Pending, $actor, [
                    'payment_method_id' => $original->payment_method_id,
                    'note' => $note.' via '.$original->method->driver.' (sending to the gateway)',
                    'meta' => ['gateway_refund' => 'processing'],
                ]);
                $remaining = round($remaining - $part, 2);
            }

            if ($remaining > 0) {
                $planned[] = $this->createRefund($booking, null, $remaining, PaymentRecordStatus::Pending, $actor, ['note' => $manualNote]);
            }

            return $planned;
        });

        foreach ($planned as $refund) {
            if ($refund->parent_payment_id !== null) {
                $this->sendToGateway($refund, $note);
            }
        }

        DB::transaction(fn () => $this->afterRefund($booking, $planned, $actor));

        return $planned;
    }

    /**
     * Calls the gateway for one recorded online refund (outside any transaction) and stores the outcome.
     */
    private function sendToGateway(Payment $refund, string $note): void
    {
        /** @var Payment $original */
        $original = $this->payments->query()->with('method')->findOrFail($refund->parent_payment_id);

        try {
            $result = $this->gateways->driver((string) $original->method->driver)->refund($original, round((float) $refund->amount * (float) $original->exchange_rate, 2));
        } catch (PaymentGatewayException $exception) {
            report($exception);

            $this->payments->update($refund, [
                'parent_payment_id' => null,
                'status' => PaymentRecordStatus::Pending,
                'note' => 'Manual refund to pay (the gateway refused the online refund: '.str($exception->getMessage())->limit(120).')',
                'meta' => ['gateway_refund' => 'failed', 'gateway' => $original->method->driver, 'original_payment_id' => $original->id],
            ]);

            return;
        }

        $this->payments->update($refund, [
            'status' => PaymentRecordStatus::Succeeded,
            'paid_at' => now(),
            'transaction_id' => $result->reference,
            'note' => $note.' via '.$original->method->driver.($result->pending ? ' (processing at the gateway)' : ''),
            'meta' => ['gateway_refund' => 'sent', 'gateway_pending' => $result->pending],
        ]);
    }

    /**
     * Marks a pending manual refund as paid.
     *
     * @throws DomainValidationException
     */
    public function markPaid(Payment $refund, User $actor, ?string $note = null): Payment
    {
        if ($refund->type !== PaymentType::Refund || $refund->status !== PaymentRecordStatus::Pending) {
            throw DomainValidationException::withErrors(['refund' => ['Only pending refunds can be marked as paid.']]);
        }

        return DB::transaction(function () use ($refund, $actor, $note): Payment {
            $this->payments->update($refund, [
                'status' => PaymentRecordStatus::Succeeded,
                'paid_at' => now(),
                'recorded_by' => $actor->id,
                'note' => trim(($refund->note ?? '').($note ? ' · '.$note : '')),
            ]);

            $this->afterRefund($refund->booking, [$refund], $actor);

            return $refund;
        });
    }

    /**
     * Payment status from received and refunded amounts.
     */
    public function syncStatus(Booking $booking, ?User $actor = null): void
    {
        $booking->unsetRelation('payments');
        $received = $this->received($booking);
        $refunded = $this->refunded($booking);

        if ($refunded <= 0) {
            return;
        }

        $fully = $refunded + 0.01 >= $received;
        $this->bookings->update($booking, ['payment_status' => $fully ? PaymentStatus::Refunded : PaymentStatus::PartiallyRefunded]);

        if ($fully && $booking->status === BookingStatus::Cancelled) {
            $this->bookingService->changeStatus($booking, BookingStatus::Refunded, $actor, 'Refunded in full');
        }
    }

    /**
     * Succeeded online payments whose gateway can refund, newest first.
     *
     * @return Collection<int, Payment>
     */
    private function onlinePayments(Booking $booking): Collection
    {
        return $this->paymentsOf($booking)
            ->filter(fn (Payment $payment): bool => $payment->type !== PaymentType::Refund
                && $payment->status === PaymentRecordStatus::Succeeded
                && filled($payment->transaction_id)
                && $payment->method?->type === PaymentMethodType::Online
                && $this->gateways->isAvailable($payment->method->driver)
                && $this->gateways->driver((string) $payment->method->driver)->supportsRefunds())
            ->sortByDesc('id')
            ->values();
    }

    private function remainingOn(Booking $booking, Payment $original): float
    {
        $refunded = $this->paymentsOf($booking)
            ->filter(fn (Payment $payment): bool => $payment->type === PaymentType::Refund && $payment->parent_payment_id === $original->id
                && in_array($payment->status, [PaymentRecordStatus::Succeeded, PaymentRecordStatus::Pending], true))
            ->sum(fn (Payment $payment): float => (float) $payment->amount);

        return max(0.0, round((float) $original->amount - $refunded, 2));
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function createRefund(Booking $booking, ?Payment $original, float $amount, PaymentRecordStatus $status, User $actor, array $extra = []): Payment
    {
        $payment = $this->payments->create([
            'booking_id' => $booking->id,
            'parent_payment_id' => $original?->id,
            'payment_method_id' => $extra['payment_method_id'] ?? null,
            'type' => PaymentType::Refund,
            'status' => $status,
            'amount' => round($amount, 2),
            'currency' => $booking->currency,
            'exchange_rate' => $booking->exchange_rate,
            'amount_in_currency' => round($amount * (float) $booking->exchange_rate, 2),
            'paid_at' => $status === PaymentRecordStatus::Succeeded ? ($extra['paid_at'] ?? now()) : null,
            'recorded_by' => $actor->id,
            'transaction_id' => $extra['transaction_id'] ?? null,
            'note' => $extra['note'] ?? null,
            'meta' => $extra['meta'] ?? null,
        ]);

        $booking->unsetRelation('payments');

        return $payment;
    }

    /**
     * @param  list<Payment>  $refunds
     */
    private function afterRefund(Booking $booking, array $refunds, User $actor): void
    {
        $this->syncStatus($booking, $actor);
        $paid = round((float) collect($refunds)->filter(fn (Payment $payment): bool => $payment->status === PaymentRecordStatus::Succeeded)->sum(fn (Payment $payment): float => (float) $payment->amount), 2);
        $total = round((float) collect($refunds)->sum(fn (Payment $payment): float => (float) $payment->amount), 2);

        if ($total > 0) {
            $this->activity->log(ActivityAction::RefundRecorded, $booking, 'Refund of '.Number::currency($total, 'EUR', 'en')." on booking {$booking->reference}", [
                'refund_ids' => array_map(fn (Payment $payment): int => $payment->id, $refunds), 'paid' => $paid, 'pending' => round($total - $paid, 2),
            ], $actor);
        }

        if ($paid > 0) {
            $locale = $booking->locale ?? 'en';
            $this->notifications->booking(NotificationEvent::RefundIssued, $booking, [
                'refund_amount' => $this->context->money($paid * (float) $booking->exchange_rate, $booking->currency, $locale === 'it' ? 'it' : 'en'),
            ]);
        }
    }

    /**
     * @return Collection<int, Payment>
     */
    private function paymentsOf(Booking $booking): Collection
    {
        return $booking->payments()->with('method')->get()->toBase();
    }
}
