<?php

namespace App\Services;

use App\DTOs\Payment\CheckoutSessionData;
use App\DTOs\Payment\PaymentMethodData;
use App\DTOs\Payment\ProofSubmissionData;
use App\DTOs\Payment\RecordPaymentData;
use App\Enums\BookingStatus;
use App\Enums\PaymentMethodType;
use App\Enums\PaymentRecordStatus;
use App\Enums\PaymentStatus;
use App\Enums\PaymentType;
use App\Enums\ProofStatus;
use App\Enums\TransferStatus;
use App\Exceptions\DomainValidationException;
use App\Models\Booking;
use App\Models\Payment;
use App\Models\PaymentMethod;
use App\Models\PaymentProof;
use App\Models\User;
use App\Repositories\Contracts\BookingRepositoryInterface;
use App\Repositories\Contracts\PaymentMethodRepositoryInterface;
use App\Repositories\Contracts\PaymentProofRepositoryInterface;
use App\Repositories\Contracts\PaymentRepositoryInterface;
use App\Services\Payments\PaymentGatewayManager;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Payments (SRS 10): methods per currency, proof of payment review, online checkout and webhooks,
 * and payments recorded by the admin. Every amount is stored in EUR with the booking's exchange rate.
 */
class PaymentService
{
    /**
     * Payment statuses in which the customer still owes the deposit.
     *
     * @var list<PaymentStatus>
     */
    public const DEPOSIT_DUE = [PaymentStatus::AwaitingPayment, PaymentStatus::ProofRejected, PaymentStatus::Failed];

    public function __construct(
        private readonly PaymentMethodRepositoryInterface $methods,
        private readonly PaymentRepositoryInterface $payments,
        private readonly PaymentProofRepositoryInterface $proofs,
        private readonly BookingRepositoryInterface $bookings,
        private readonly BookingService $bookingService,
        private readonly TransferService $transfers,
        private readonly CurrencyService $currencies,
        private readonly PaymentGatewayManager $gateways,
    ) {}

    /**
     * @return Collection<int, PaymentMethod>
     */
    public function methodsFor(Booking $booking): Collection
    {
        return $this->methods->enabledForCurrency($booking->currency);
    }

    /**
     * What the customer has to pay now: the deposit, or the balance once the deposit is paid (EUR).
     */
    public function amountDueNow(Booking $booking): float
    {
        if (in_array($booking->payment_status, [...self::DEPOSIT_DUE, PaymentStatus::AwaitingReceiptReview], true)) {
            return (float) $booking->deposit_amount;
        }

        return max(0.0, $booking->dueAmount());
    }

    /**
     * Amount in the booking currency at the booking's exchange rate.
     */
    public function inBookingCurrency(Booking $booking, float $amountEur): float
    {
        return round($amountEur * (float) $booking->exchange_rate, 2);
    }

    /**
     * Stores the proof files on the private disk and puts the booking in "awaiting receipt review".
     *
     * @throws DomainValidationException
     */
    public function submitProof(Booking $booking, ProofSubmissionData $data): PaymentProof
    {
        $method = $this->methods->findEnabled($data->paymentMethodId);

        if ($method === null || ! $method->isManual() || ! $method->supportsCurrency($booking->currency)) {
            throw DomainValidationException::withErrors(['payment_method_id' => ['This payment method is not available for this booking.']]);
        }

        if (in_array($booking->status, BookingStatus::voided(), true) || $booking->payment_status === PaymentStatus::FullyPaid) {
            throw DomainValidationException::withErrors(['files' => ['This booking does not expect a payment.']]);
        }

        if ($booking->proofs()->where('status', ProofStatus::Pending)->exists()) {
            throw DomainValidationException::withErrors(['files' => ['A proof of payment is already awaiting review.']]);
        }

        $purpose = in_array($booking->payment_status, self::DEPOSIT_DUE, true) ? PaymentType::Deposit : PaymentType::Balance;
        $disk = (string) config('payments.proofs.disk', PaymentProof::DISK);
        $files = [];

        foreach ($data->files as $file) {
            $extension = $file->guessExtension() ?: 'bin';
            $files[] = [
                'path' => $file->storeAs("payment-proofs/{$booking->id}", Str::uuid().'.'.$extension, $disk),
                'name' => Str::limit($file->getClientOriginalName(), 120, ''),
                'mime' => (string) $file->getMimeType(),
                'size' => (int) $file->getSize(),
            ];
        }

        return DB::transaction(function () use ($booking, $data, $method, $purpose, $files): PaymentProof {
            $proof = $this->proofs->create([
                'booking_id' => $booking->id,
                'payment_method_id' => $method->id,
                'purpose' => $purpose,
                'files' => $files,
                'transaction_number' => $data->transactionNumber,
                'transaction_date' => $data->transactionDate?->toDateString(),
                'amount' => $data->amount,
                'sender_name' => $data->senderName,
                'status' => ProofStatus::Pending,
                'uploaded_by' => $data->uploadedBy,
            ]);

            $this->bookings->update($booking, ['payment_status' => PaymentStatus::AwaitingReceiptReview]);

            if ($booking->status === BookingStatus::AwaitingPayment) {
                $this->bookingService->changeStatus($booking, BookingStatus::AwaitingReceiptReview, null, 'Proof of payment uploaded');
            }

            $this->transfers->syncWithPayment($booking, TransferStatus::AwaitingReceiptReview);

            return $proof;
        });
    }

    /**
     * Approves a proof: records the payment (deposit or balance) and confirms the booking.
     */
    public function approveProof(PaymentProof $proof, User $reviewer): Payment
    {
        if ($proof->status !== ProofStatus::Pending) {
            throw DomainValidationException::withErrors(['proof' => ['This proof has already been reviewed.']]);
        }

        return DB::transaction(function () use ($proof, $reviewer): Payment {
            $booking = $proof->booking;
            $amount = $proof->purpose === PaymentType::Deposit ? (float) $booking->deposit_amount : max(0.0, $booking->dueAmount());

            $payment = $this->createSucceededPayment($booking, $proof->purpose, $amount, $proof->payment_method_id, $reviewer, [
                'transaction_id' => $proof->transaction_number,
                'note' => 'Proof of payment approved',
                'meta' => ['proof_id' => $proof->id, 'declared_amount' => $proof->amount !== null ? (float) $proof->amount : null, 'sender_name' => $proof->sender_name],
            ]);

            $this->proofs->update($proof, [
                'status' => ProofStatus::Approved,
                'payment_id' => $payment->id,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => null,
            ]);

            $this->settle($booking, $reviewer, 'Proof of payment approved');

            return $payment;
        });
    }

    public function rejectProof(PaymentProof $proof, User $reviewer, string $reason): PaymentProof
    {
        if ($proof->status !== ProofStatus::Pending) {
            throw DomainValidationException::withErrors(['proof' => ['This proof has already been reviewed.']]);
        }

        return DB::transaction(function () use ($proof, $reviewer, $reason): PaymentProof {
            $this->proofs->update($proof, [
                'status' => ProofStatus::Rejected,
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'rejection_reason' => $reason,
            ]);

            $booking = $proof->booking;
            $this->bookings->update($booking, [
                'payment_status' => $proof->purpose === PaymentType::Deposit ? PaymentStatus::ProofRejected : PaymentStatus::DepositPaid,
            ]);

            if ($booking->status === BookingStatus::AwaitingReceiptReview) {
                $this->bookingService->changeStatus($booking, BookingStatus::AwaitingPayment, $reviewer, 'Proof rejected: '.$reason);
            }

            $this->transfers->syncWithPayment($booking, TransferStatus::AwaitingPayment);

            return $proof;
        });
    }

    /**
     * A payment received outside the site (cash, transfer...), recorded by the admin. Closes the account at zero balance.
     */
    public function recordPayment(Booking $booking, RecordPaymentData $data, User $actor): Payment
    {
        $due = $booking->dueAmount();

        if ($data->amount <= 0 || $data->amount > round($due + 0.01, 2)) {
            throw DomainValidationException::withErrors(['amount' => ['The amount must be between 0.01 and the balance due (€'.number_format($due, 2).').']]);
        }

        return DB::transaction(function () use ($booking, $data, $actor): Payment {
            $payment = $this->createSucceededPayment($booking, $data->type, $data->amount, $data->paymentMethodId, $actor, [
                'paid_at' => $data->paidAt,
                'note' => $data->note,
                'transaction_id' => $data->transactionId,
            ]);

            $this->settle($booking, $actor, $data->type === PaymentType::Balance ? 'Balance payment recorded' : 'Payment recorded');

            return $payment;
        });
    }

    /**
     * Starts a hosted checkout with an online gateway and logs the attempt as a pending payment.
     */
    public function startCheckout(Booking $booking, int $paymentMethodId): CheckoutSessionData
    {
        $method = $this->methods->findEnabled($paymentMethodId);

        if ($method === null || $method->type !== PaymentMethodType::Online || ! $method->supportsCurrency($booking->currency) || ! $this->gateways->has((string) $method->driver)) {
            throw DomainValidationException::withErrors(['payment_method_id' => ['This payment method is not available for this booking.']]);
        }

        $amount = $this->amountDueNow($booking);

        if ($amount <= 0 || in_array($booking->status, BookingStatus::voided(), true) || $booking->payment_status === PaymentStatus::AwaitingReceiptReview) {
            throw DomainValidationException::withErrors(['payment_method_id' => ['This booking does not expect an online payment now.']]);
        }

        $amount = round($amount * (1 + (float) $method->surcharge_percent / 100), 2);
        $type = in_array($booking->payment_status, self::DEPOSIT_DUE, true) ? PaymentType::Deposit : PaymentType::Balance;

        return DB::transaction(function () use ($booking, $method, $amount, $type): CheckoutSessionData {
            $payment = $this->payments->create([
                'booking_id' => $booking->id,
                'payment_method_id' => $method->id,
                'type' => $type,
                'status' => PaymentRecordStatus::Pending,
                'amount' => $amount,
                'currency' => $booking->currency,
                'exchange_rate' => $booking->exchange_rate,
                'amount_in_currency' => $this->inBookingCurrency($booking, $amount),
            ]);

            $session = $this->gateways->driver((string) $method->driver)->createCheckout($booking, $payment, $method);
            $this->payments->update($payment, ['gateway_reference' => $session->reference, 'meta' => ['checkout_url' => $session->url]]);

            return $session;
        });
    }

    /**
     * Applies a verified gateway webhook. Unknown or repeated events are ignored (idempotent).
     *
     * @return bool false when the signature is invalid
     */
    public function handleWebhook(string $driver, string $payload, ?string $signature): bool
    {
        if (! $this->gateways->has($driver)) {
            return false;
        }

        $gateway = $this->gateways->driver($driver);

        if (! $gateway->verifyWebhook($payload, $signature)) {
            return false;
        }

        $event = $gateway->parseWebhook($payload);

        if ($event->succeeded === null || $event->reference === null) {
            return true;
        }

        $payment = $this->payments->findPendingByGatewayReference($event->reference);

        if ($payment === null || $payment->status !== PaymentRecordStatus::Pending) {
            return true;
        }

        DB::transaction(function () use ($payment, $event): void {
            $booking = $payment->booking;

            if ($event->succeeded) {
                $this->payments->update($payment, ['status' => PaymentRecordStatus::Succeeded, 'paid_at' => now(), 'transaction_id' => $event->transactionId]);
                $booking->unsetRelation('payments');
                $this->settle($booking, null, 'Online payment confirmed');

                return;
            }

            $this->payments->update($payment, ['status' => PaymentRecordStatus::Failed, 'transaction_id' => $event->transactionId]);

            if ($booking->payment_status === PaymentStatus::AwaitingPayment) {
                $this->bookings->update($booking, ['payment_status' => PaymentStatus::Failed]);
            }
        });

        return true;
    }

    public function createMethod(PaymentMethodData $data): PaymentMethod
    {
        return $this->methods->create($data->attributes);
    }

    public function updateMethod(PaymentMethod $method, PaymentMethodData $data): PaymentMethod
    {
        return $this->methods->update($method, $data->attributes);
    }

    public function deleteMethod(PaymentMethod $method): void
    {
        $this->methods->delete($method);
    }

    /**
     * @return Builder<PaymentMethod>
     */
    public function methodsQuery(): Builder
    {
        return $this->methods->adminQuery();
    }

    /**
     * @return Builder<Payment>
     */
    public function paymentsQuery(?string $status = null): Builder
    {
        return $this->payments->adminQuery($status);
    }

    /**
     * @return Builder<PaymentProof>
     */
    public function proofsQuery(?string $status = null): Builder
    {
        return $this->proofs->adminQuery($status);
    }

    /**
     * @return array<int, string>
     */
    public function methodOptions(): array
    {
        return $this->methods->selectOptions();
    }

    /**
     * Absolute path and metadata of a proof file on the private disk, or null when missing.
     *
     * @return array{path: string, name: string, mime: string}|null
     */
    public function proofFile(PaymentProof $proof, int $index): ?array
    {
        $file = $proof->file($index);
        $disk = Storage::disk((string) config('payments.proofs.disk', PaymentProof::DISK));

        if ($file === null || ! $disk->exists($file['path'])) {
            return null;
        }

        return ['path' => $disk->path($file['path']), 'name' => $file['name'], 'mime' => $file['mime']];
    }

    /**
     * @param  array<string, mixed>  $extra
     */
    private function createSucceededPayment(Booking $booking, PaymentType $type, float $amount, ?int $methodId, ?User $actor, array $extra = []): Payment
    {
        $payment = $this->payments->create([
            'booking_id' => $booking->id,
            'payment_method_id' => $methodId,
            'type' => $type,
            'status' => PaymentRecordStatus::Succeeded,
            'amount' => round($amount, 2),
            'currency' => $booking->currency,
            'exchange_rate' => $booking->exchange_rate,
            'amount_in_currency' => $this->inBookingCurrency($booking, $amount),
            'paid_at' => $extra['paid_at'] ?? now(),
            'recorded_by' => $actor?->id,
            'transaction_id' => $extra['transaction_id'] ?? null,
            'note' => $extra['note'] ?? null,
            'meta' => $extra['meta'] ?? null,
        ]);

        $booking->unsetRelation('payments');

        return $payment;
    }

    /**
     * Payment status and booking status from the recorded payments: deposit paid -> confirmed, nothing due -> fully paid.
     */
    private function settle(Booking $booking, ?User $actor, string $note): void
    {
        $booking->unsetRelation('payments');
        $paid = $booking->paidAmount();
        $fullyPaid = $paid + 0.01 >= (float) $booking->total;

        $this->bookings->update($booking, ['payment_status' => $fullyPaid ? PaymentStatus::FullyPaid : PaymentStatus::DepositPaid]);

        $target = $fullyPaid ? BookingStatus::FullyPaid : BookingStatus::Confirmed;
        $canMove = in_array($booking->status, [BookingStatus::AwaitingPayment, BookingStatus::AwaitingReceiptReview], true)
            || ($fullyPaid && $booking->status === BookingStatus::Confirmed);

        if ($canMove && $booking->status !== $target) {
            $this->bookingService->changeStatus($booking, $target, $actor, $note);
        }

        $this->transfers->syncWithPayment($booking, TransferStatus::Paid);
    }
}
