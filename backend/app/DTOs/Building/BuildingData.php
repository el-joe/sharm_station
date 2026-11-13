<?php

namespace App\DTOs\Building;

use App\Enums\BuildingType;
use App\Enums\PublishStatus;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin create/update payload for a building.
 */
final readonly class BuildingData
{
    /**
     * @param  array<string, string>  $name
     * @param  array<string, mixed>  $attributes  Remaining columns (address, description, amenities, check-in times...).
     */
    public function __construct(
        public array $name,
        public string $slug,
        public BuildingType $type,
        public int $destinationId,
        public bool $taxesIncluded = true,
        public ?float $taxPerNight = null,
        public ?string $googlePlaceId = null,
        public float $depositPercent = 30,
        public PublishStatus $status = PublishStatus::Draft,
        public array $attributes = [],
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $known = ['name', 'slug', 'type', 'destination_id', 'taxes_included', 'tax_per_night', 'google_place_id', 'deposit_percent', 'status'];
        $taxesIncluded = (bool) ($data['taxes_included'] ?? true);

        return new self(
            name: is_array($data['name']) ? $data['name'] : ['en' => (string) $data['name']],
            slug: $data['slug'],
            type: BuildingType::from($data['type'] ?? BuildingType::Hotel->value),
            destinationId: (int) $data['destination_id'],
            taxesIncluded: $taxesIncluded,
            taxPerNight: ! $taxesIncluded && isset($data['tax_per_night']) ? (float) $data['tax_per_night'] : null,
            googlePlaceId: $data['google_place_id'] ?? null,
            depositPercent: (float) ($data['deposit_percent'] ?? 30),
            status: PublishStatus::from($data['status'] ?? PublishStatus::Draft->value),
            attributes: array_diff_key($data, array_flip($known)),
        );
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            ...$this->attributes,
            'name' => $this->name,
            'slug' => $this->slug,
            'type' => $this->type,
            'destination_id' => $this->destinationId,
            'taxes_included' => $this->taxesIncluded,
            'tax_per_night' => $this->taxPerNight,
            'google_place_id' => $this->googlePlaceId,
            'deposit_percent' => $this->depositPercent,
            'status' => $this->status,
        ];
    }
}
