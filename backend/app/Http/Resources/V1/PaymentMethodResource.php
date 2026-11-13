<?php

namespace App\Http\Resources\V1;

use App\Models\Booking;
use App\Models\PaymentMethod;
use App\Services\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Payment method offered for a booking, with the amount due now in the booking currency. Credentials are never exposed.
 *
 * @mixin PaymentMethod
 */
class PaymentMethodResource extends JsonResource
{
    public ?Booking $booking = null;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $amount = null;

        if ($this->booking !== null) {
            $service = app(PaymentService::class);
            $dueEur = round($service->amountDueNow($this->booking) * (1 + (float) $this->surcharge_percent / 100), 2);
            $amount = ['amount_eur' => $dueEur, 'amount' => $service->inBookingCurrency($this->booking, $dueEur), 'currency' => $this->booking->currency];
        }

        return [
            'id' => $this->id,
            'code' => $this->code,
            'type' => $this->type->value,
            'group' => $this->type->value === 'online' ? 'instant' : 'manual_transfer',
            'name' => $this->name,
            'instructions' => $this->instructions,
            'account_details' => $this->account_details ?? [],
            'logo' => $this->logo,
            'currencies' => $this->currencies ?? [],
            'proof_required' => $this->proof_required,
            'surcharge_percent' => (float) $this->surcharge_percent,
            'deadline_hours' => $this->deadline_hours,
            'amount_due' => $amount,
            'booking_reference' => $this->booking?->reference,
        ];
    }
}
