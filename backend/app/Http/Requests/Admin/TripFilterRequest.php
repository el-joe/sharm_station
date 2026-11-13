<?php

namespace App\Http\Requests\Admin;

use App\Enums\PricingMode;
use App\Enums\PublishStatus;
use App\Enums\TripType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TripFilterRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'scope' => ['nullable', Rule::in(['trips', 'nile'])],
            'type' => ['nullable', Rule::enum(TripType::class)],
            'destination_id' => ['nullable', 'integer'],
            'status' => ['nullable', Rule::enum(PublishStatus::class)],
            'pricing_mode' => ['nullable', Rule::enum(PricingMode::class)],
        ];
    }
}
