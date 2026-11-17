<?php

namespace App\Http\Resources\V1;

use App\Models\User;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin User
 */
class UserResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'email' => $this->email,
            'phone' => $this->phone,
            'role' => $this->role->value,
            'locale' => $this->locale,
            'currency' => $this->currency,
            'newsletter' => (bool) $this->newsletter,
            'nationality_code' => $this->nationality_code,
            'partner' => $this->whenLoaded('partner', fn (): ?array => $this->partner ? [
                'id' => $this->partner->id,
                'company' => $this->partner->company,
                'status' => $this->partner->status->value,
                'show_retail_price' => $this->partner->show_retail_price,
            ] : null),
        ];
    }
}
