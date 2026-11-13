<?php

namespace App\DTOs\Transfer;

use App\Enums\TransferDirection;
use App\Models\Partner;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final readonly class TransferQuoteRequestData
{
    /**
     * @param  list<string>  $extras  hub extra codes (child_seat, extra_stop...)
     */
    public function __construct(
        public int $hubId,
        public int $vehicleTypeId,
        public string $destinationName,
        public float $destinationLat,
        public float $destinationLng,
        public CarbonImmutable $pickupAt,
        public int $passengers,
        public int $luggage = 0,
        public bool $roundTrip = false,
        public ?CarbonImmutable $returnAt = null,
        public array $extras = [],
        public int $waitingMinutes = 0,
        public TransferDirection $direction = TransferDirection::FromHub,
        public ?string $flightNumber = null,
        public ?Partner $partner = null,
        public string $currency = 'EUR',
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data, ?Partner $partner = null, string $currency = 'EUR'): self
    {
        $roundTrip = (bool) ($data['round_trip'] ?? false);

        return new self(
            hubId: (int) $data['hub_id'],
            vehicleTypeId: (int) $data['vehicle_type_id'],
            destinationName: (string) $data['destination']['name'],
            destinationLat: (float) $data['destination']['lat'],
            destinationLng: (float) $data['destination']['lng'],
            pickupAt: CarbonImmutable::parse($data['pickup_at']),
            passengers: (int) $data['passengers'],
            luggage: (int) ($data['luggage'] ?? 0),
            roundTrip: $roundTrip,
            returnAt: $roundTrip && filled($data['return_at'] ?? null) ? CarbonImmutable::parse($data['return_at']) : null,
            extras: array_values(array_unique(array_map('strval', $data['extras'] ?? []))),
            waitingMinutes: (int) ($data['waiting_minutes'] ?? 0),
            direction: TransferDirection::tryFrom((string) ($data['direction'] ?? '')) ?? TransferDirection::FromHub,
            flightNumber: $data['flight_number'] ?? null,
            partner: $partner,
            currency: $currency,
        );
    }

    public static function fromRequest(FormRequest $request, ?Partner $partner = null, string $currency = 'EUR'): self
    {
        return self::fromArray($request->validated(), $partner, $currency);
    }
}
