<?php

namespace App\Http\Requests\Api;

use App\DTOs\Booking\CreateBookingData;
use App\Enums\ServiceType;
use App\Enums\TravelerType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * A trip booking (trip_slug + trip quote fields), a package booking (package_slug + per-person fields),
 * an accommodation booking (room_id + stay fields) or a transfer booking (transfer.{quote fields}).
 * "service" is optional and only used to pick the product when it cannot be inferred.
 * "nationality_code" (ISO-2, required) sets the nationality group of the price (CR-10).
 */
class StoreBookingRequest extends FormRequest
{
    public function authorize(): bool
    {
        return true;
    }

    protected function prepareForValidation(): void
    {
        if (is_string($this->input('nationality_code'))) {
            $this->merge(['nationality_code' => strtoupper($this->input('nationality_code'))]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $service = CreateBookingData::serviceFor($this->all());
        $isTransfer = $service === ServiceType::Transfer;

        $common = [
            'service' => ['nullable', Rule::enum(ServiceType::class)],
            'nationality_code' => ['required', 'string', 'size:2', Rule::exists('countries', 'code')],
            'trip_slug' => [Rule::requiredIf($service === ServiceType::Trip), 'nullable', 'prohibits:room_id', 'string', 'max:255'],
            'room_id' => [Rule::requiredIf($service === ServiceType::Accommodation && ! $this->has('rooms')), 'nullable', 'integer'],
            'package_slug' => ['nullable', 'string', 'max:255'],
            'lead' => ['required', 'array'],
            'lead.name' => ['required', 'string', 'max:255'],
            'lead.email' => ['required', 'email', 'max:255'],
            'lead.phone' => [$isTransfer ? 'required' : 'nullable', 'string', 'max:40'],
            'travelers' => [$isTransfer ? 'nullable' : 'required', 'array', 'min:1', 'max:100'],
            'travelers.*.first_name' => ['required', 'string', 'max:100'],
            'travelers.*.last_name' => ['required', 'string', 'max:100'],
            'travelers.*.type' => ['required', Rule::enum(TravelerType::class)],
            'travelers.*.age' => ['nullable', 'integer', 'min:0', 'max:120'],
            'travelers.*.nationality' => ['nullable', 'string', 'size:2'],
            'travelers.*.passport_number' => ['nullable', 'string', 'max:40'],
            'notes' => ['nullable', 'string', 'max:2000'],
            'coupon_code' => ['nullable', 'string', 'max:40'],
        ];

        $serviceRules = match ($service) {
            ServiceType::Accommodation => RoomQuoteRequest::stayRules(),
            ServiceType::Package => ['package_slug' => ['required', 'string', 'max:255'], ...PackageQuoteRequest::quoteRules()],
            ServiceType::Transfer => ['transfer' => ['required', 'array'], ...TransferQuoteRequest::quoteRules('transfer.')],
            default => TripQuoteRequest::quoteRules(),
        };

        return [...$common, ...$serviceRules];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return RoomQuoteRequest::stayMessages();
    }
}
