<?php

namespace App\DTOs\Booking;

use App\Enums\TravelerType;

final readonly class TravelerData
{
    public function __construct(
        public string $firstName,
        public string $lastName,
        public TravelerType $type,
        public ?int $age = null,
        public ?string $nationality = null,
        public ?string $passportNumber = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            firstName: $data['first_name'],
            lastName: $data['last_name'],
            type: TravelerType::from($data['type'] ?? TravelerType::Adult->value),
            age: isset($data['age']) ? (int) $data['age'] : null,
            nationality: $data['nationality'] ?? null,
            passportNumber: $data['passport_number'] ?? null,
        );
    }
}
