<?php

namespace App\Http\Resources\V1;

use App\Models\Partner;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Partner
 */
class PartnerProfileResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'company' => $this->company,
            'tax_number' => $this->tax_number,
            'contact_name' => $this->contact_name ?? $this->user?->name,
            'email' => $this->user?->email,
            'phone' => $this->phone ?? $this->user?->phone,
            'country' => $this->country,
            'website' => $this->website,
            'status' => $this->status->value,
            'notify_bookings' => (bool) $this->notify_bookings,
            'notify_statements' => (bool) $this->notify_statements,
            'price_list_enabled' => (bool) $this->price_list_enabled,
        ];
    }
}
