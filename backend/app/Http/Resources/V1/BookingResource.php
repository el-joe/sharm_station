<?php

namespace App\Http\Resources\V1;

use App\Enums\PaymentStatus;
use App\Models\Booking;
use App\Models\BookingAddon;
use App\Models\BookingIncludedTransfer;
use App\Models\BookingRoom;
use App\Models\BookingTraveler;
use App\Models\BookingUnit;
use App\Models\Payment;
use App\Models\PaymentProof;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Partner totals are only shown to the partner who made the booking (or staff).
 *
 * @mixin Booking
 */
class BookingResource extends JsonResource
{
    /**
     * @var list<array<string, mixed>>|null
     */
    public ?array $paymentOptions = null;

    public ?string $accessToken = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $viewer = $request->user('sanctum');
        $showPartner = $this->partner_id !== null && $viewer !== null
            && ($viewer->isStaff() || $viewer->partner?->id === $this->partner_id);
        // The end client of a partner booking never sees what the partner pays (payments and options are at partner price).
        $pricesHidden = $this->partner_id !== null && ! $showPartner;

        return [
            'reference' => $this->reference,
            'access_token' => $this->when($this->accessToken !== null, $this->accessToken),
            'status' => $this->status->value,
            'payment_status' => $this->payment_status->value,
            'payment_due_at' => $this->payment_due_at?->toIso8601String(),
            'service_type' => $this->service_type->value,
            'booking_type' => $this->booking_type?->value,
            'pricing_mode' => $this->pricing_mode?->value,
            'season_name' => $this->season_name,
            'nationality' => [
                'code' => $this->nationality_code,
                'group' => $this->nationalityGroup?->summary(),
            ],
            'product' => $this->bookable ? [
                'type' => $this->bookable_type,
                'id' => $this->bookable_id,
                'title' => $this->bookable->title ?? $this->bookable->name,
                'slug' => $this->bookable->slug ?? null,
            ] : null,
            'start_date' => $this->start_date?->toDateString(),
            'check_in' => $this->check_in?->toDateString(),
            'check_out' => $this->check_out?->toDateString(),
            'lead' => ['name' => $this->lead_name, 'email' => $this->lead_email, 'phone' => $this->lead_phone],
            'adults' => $this->adults,
            'children' => $this->children,
            'travelers' => $this->travelers->map(fn (BookingTraveler $traveler): array => [
                'first_name' => $traveler->first_name,
                'last_name' => $traveler->last_name,
                'type' => $traveler->type->value,
                'age' => $traveler->age,
            ])->values(),
            'units' => $this->units->map(fn (BookingUnit $unit): array => [
                'name' => $unit->name,
                'seats' => $unit->seats,
                'quantity' => $unit->quantity,
                'unit_price' => (float) $unit->unit_price,
                'total' => (float) $unit->total,
            ])->values(),
            'addons' => $this->addons->map(fn (BookingAddon $addon): array => [
                'name' => $addon->name,
                'pricing_basis' => $addon->pricing_basis->value,
                'quantity' => $addon->quantity,
                'unit_price' => (float) $addon->unit_price,
                'total' => (float) $addon->total,
            ])->values(),
            'rooms' => $this->rooms->map(fn (BookingRoom $room): array => [
                'room_id' => $room->room_id,
                'name' => $room->room_name,
                'nights' => $room->nights,
                'price' => (float) $room->price,
                'total' => (float) $room->total,
            ])->values(),
            'lines' => array_map(
                fn (array $line): array => $showPartner ? $line : array_diff_key($line, array_flip(['partner_unit_price', 'partner_total', 'price_rule', 'commission_percent'])),
                $this->price_lines ?? [],
            ),
            'totals' => $pricesHidden
                ? ['retail_total' => (float) $this->retail_total, 'base_currency' => 'EUR', 'prices_hidden' => true]
                : [
                    'retail_total' => (float) $this->retail_total,
                    'total' => (float) $this->total,
                    'deposit_percent' => (float) $this->deposit_percent,
                    'deposit_amount' => (float) $this->deposit_amount,
                    'balance_amount' => (float) $this->balance_amount,
                    'base_currency' => 'EUR',
                    'currency' => $this->currency,
                    'exchange_rate' => (float) $this->exchange_rate,
                    'total_in_currency' => (float) $this->total_in_currency,
                    ...($showPartner ? [
                        'partner_total' => (float) $this->partner_total,
                        'commission_amount' => (float) $this->commission_amount,
                        'commission_percent' => $this->commission_percent !== null ? (float) $this->commission_percent : null,
                        'commission_source' => $this->price_rule?->value,
                        'price_rule' => $this->price_rule?->value,
                    ] : []),
                ],
            'payment_options' => $this->when($this->paymentOptions !== null && ! $pricesHidden, $this->paymentOptions),
            'payment_summary' => $pricesHidden ? null : [
                'paid' => $this->paidAmount(),
                'due' => $this->dueAmount(),
                'deposit_due' => in_array($this->payment_status, [PaymentStatus::AwaitingPayment, PaymentStatus::ProofRejected, PaymentStatus::Failed, PaymentStatus::AwaitingReceiptReview], true),
                'currency' => $this->currency,
            ],
            'payments' => $pricesHidden ? [] : $this->payments->map(fn (Payment $payment): array => [
                'type' => $payment->type->value,
                'status' => $payment->status->value,
                'amount' => (float) $payment->amount,
                'currency' => $payment->currency,
                'amount_in_currency' => (float) $payment->amount_in_currency,
                'method' => $payment->method ? (string) $payment->method->name : null,
                'paid_at' => $payment->paid_at?->toIso8601String(),
            ])->values(),
            'proofs' => $this->proofs->map(fn (PaymentProof $proof): array => [
                'id' => $proof->id,
                'status' => $proof->status->value,
                'purpose' => $proof->purpose->value,
                'files_count' => count($proof->files ?? []),
                'rejection_reason' => $proof->rejection_reason,
                'created_at' => $proof->created_at?->toIso8601String(),
                'reviewed_at' => $proof->reviewed_at?->toIso8601String(),
            ])->values(),
            'included_transfers' => $this->includedTransfers->map(fn (BookingIncludedTransfer $transfer): array => [
                'date' => $transfer->date->toDateString(),
                'time' => $transfer->time ? substr((string) $transfer->time, 0, 5) : null,
                'from' => $transfer->from_place,
                'to' => $transfer->to_place,
                'vehicle_type' => $transfer->vehicle_type?->value,
                'status' => $transfer->status->value,
                'transfer_included' => true,
            ])->values(),
            'transfer' => $this->transferRequest ? [
                'hub_id' => $this->transferRequest->transfer_hub_id,
                'vehicle_type_id' => $this->transferRequest->vehicle_type_id,
                'direction' => $this->transferRequest->direction->value,
                'destination' => ['name' => $this->transferRequest->destination_name, 'lat' => $this->transferRequest->destination_lat, 'lng' => $this->transferRequest->destination_lng],
                'pickup_at' => $this->transferRequest->pickup_at->format('Y-m-d H:i'),
                'return_at' => $this->transferRequest->return_at?->format('Y-m-d H:i'),
                'round_trip' => $this->transferRequest->round_trip,
                'passengers' => $this->transferRequest->passengers,
                'luggage' => $this->transferRequest->luggage,
                'extras' => $this->transferRequest->extras ?? [],
                'flight_number' => $this->transferRequest->flight_number,
                'distance_km' => (float) $this->transferRequest->distance_km,
                'duration_minutes' => $this->transferRequest->duration_minutes,
                'status' => $this->transferRequest->status->value,
                'driver' => $this->transferRequest->driver_name ? [
                    'name' => $this->transferRequest->driver_name,
                    'phone' => $this->transferRequest->driver_phone,
                    'car_type' => $this->transferRequest->car_type,
                    'car_plate' => $this->transferRequest->car_plate,
                ] : null,
            ] : null,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
