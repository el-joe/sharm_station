<?php

namespace App\DTOs\Package;

use App\Enums\VehicleType;

/**
 * Template of an included transfer (no price). day_number is the package day.
 */
final readonly class IncludedTransferData
{
    public function __construct(
        public int $dayNumber,
        public string $fromPlace,
        public string $toPlace,
        public ?string $time = null,
        public ?VehicleType $vehicleType = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            dayNumber: (int) $data['day_number'],
            fromPlace: (string) $data['from_place'],
            toPlace: (string) $data['to_place'],
            time: filled($data['time'] ?? null) ? substr((string) $data['time'], 0, 5) : null,
            vehicleType: filled($data['vehicle_type'] ?? null) ? VehicleType::from($data['vehicle_type']) : null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'day_number' => $this->dayNumber,
            'from_place' => $this->fromPlace,
            'to_place' => $this->toPlace,
            'time' => $this->time,
            'vehicle_type' => $this->vehicleType,
        ];
    }
}
