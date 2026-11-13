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
 */
class StoreBookingRequest extends FormRequest
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
        $service = CreateBookingData::serviceFor($this->all());
        $isTransfer = $service === ServiceType::Transfer;

        $common = [
            'service' => ['nullable', Rule::enum(ServiceType::class)],
            'trip_slug' => ['required_without_all:room_id,package_slug,transfer', 'prohibits:room_id', 'string', 'max:255'],
            'room_id' => ['required_without_all:trip_slug,package_slug,transfer', 'integer'],
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
        ];

        $serviceRules = match ($service) {
            ServiceType::Accommodation => RoomQuoteRequest::stayRules(),
            ServiceType::Package => ['package_slug' => ['required', 'string', 'max:255'], ...PackageQuoteRequest::quoteRules()],
            ServiceType::Transfer => ['transfer' => ['required', 'array'], ...TransferQuoteRequest::quoteRules('transfer.')],
            default => TripQuoteRequest::quoteRules(),
        };

        return [...$common, ...$serviceRules];
    }
}
