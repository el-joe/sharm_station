<?php

namespace App\Http\Resources\V1;

use App\Http\Resources\V1\Concerns\PresentsLabels;
use App\Models\VehicleType;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin VehicleType
 */
class VehicleTypeResource extends JsonResource
{
    use PresentsLabels;

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'name' => $this->name,
            'category' => $this->category->value,
            'category_label' => $this->catalogLabel('vehicles', $this->category->value),
            'capacity' => $this->capacity,
            'luggage' => $this->luggage,
            'image' => $this->image,
        ];
    }
}
