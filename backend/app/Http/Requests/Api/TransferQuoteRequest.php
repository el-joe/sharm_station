<?php

namespace App\Http\Requests\Api;

use App\Enums\TransferDirection;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class TransferQuoteRequest extends FormRequest
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
        return self::quoteRules();
    }

    /**
     * Shared with the booking request, where the fields sit under "transfer.".
     *
     * @return array<string, mixed>
     */
    public static function quoteRules(string $prefix = ''): array
    {
        return [
            $prefix.'hub_id' => ['required', 'integer'],
            $prefix.'vehicle_type_id' => ['required', 'integer'],
            $prefix.'direction' => ['nullable', Rule::enum(TransferDirection::class)],
            $prefix.'destination' => ['required', 'array'],
            $prefix.'destination.name' => ['required', 'string', 'max:255'],
            $prefix.'destination.lat' => ['required', 'numeric', 'between:-90,90'],
            $prefix.'destination.lng' => ['required', 'numeric', 'between:-180,180'],
            $prefix.'pickup_at' => ['required', 'date_format:Y-m-d H:i'],
            $prefix.'round_trip' => ['nullable', 'boolean'],
            $prefix.'return_at' => ['nullable', 'required_if_accepted:'.$prefix.'round_trip', 'date_format:Y-m-d H:i'],
            $prefix.'passengers' => ['required', 'integer', 'min:1', 'max:60'],
            $prefix.'luggage' => ['nullable', 'integer', 'min:0', 'max:60'],
            $prefix.'extras' => ['nullable', 'array', 'max:10'],
            $prefix.'extras.*' => ['string', 'max:40'],
            $prefix.'waiting_minutes' => ['nullable', 'integer', 'min:0', 'max:600'],
            $prefix.'flight_number' => ['nullable', 'string', 'max:20'],
        ];
    }
}
