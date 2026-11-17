<?php

namespace App\DTOs\Trip;

use App\Enums\BookingType;
use App\Enums\PricingMode;
use App\Enums\PublishStatus;
use App\Enums\TripType;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Admin create/update payload for a trip or Nile trip. Translatable fields are {locale: value} maps.
 */
final readonly class TripData
{
    /**
     * @param  array<string, mixed>  $attributes  Remaining scalar/JSON columns (summary, description, highlights, Nile fields...).
     * @param  list<UnitOptionData>|null  $unitOptions  null = leave unchanged.
     * @param  list<PrivateTierData>|null  $privateTiers  null = leave unchanged.
     * @param  list<AvailabilityRuleData>|null  $availability  null = leave unchanged.
     * @param  list<TripDayData>|null  $days  Itinerary days (payload key "itinerary"); null = leave unchanged.
     * @param  list<int>|null  $addonIds  null = leave unchanged.
     */
    public function __construct(
        public TripType $type,
        public int $destinationId,
        public array $title,
        public string $slug,
        public PricingMode $pricingMode,
        public bool $privateEnabled,
        public bool $groupEnabled,
        public int $minPersons = 1,
        public ?int $maxPersons = null,
        public ?float $basePrice = null,
        public ?float $baseChildPrice = null,
        public float $depositPercent = 30,
        public PublishStatus $status = PublishStatus::Draft,
        public array $attributes = [],
        public ?array $unitOptions = null,
        public ?array $privateTiers = null,
        public ?array $availability = null,
        public ?array $days = null,
        public ?array $addonIds = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $known = ['type', 'destination_id', 'title', 'slug', 'pricing_mode', 'private_enabled', 'group_enabled', 'min_persons',
            'max_persons', 'base_price', 'base_child_price', 'deposit_percent', 'status', 'unit_options', 'private_tiers', 'availability', 'itinerary', 'addon_ids'];
        $days = isset($data['itinerary']) ? array_values($data['itinerary']) : null;

        return new self(
            type: TripType::from($data['type']),
            destinationId: (int) $data['destination_id'],
            title: is_array($data['title']) ? $data['title'] : ['en' => (string) $data['title']],
            slug: $data['slug'],
            pricingMode: PricingMode::from($data['pricing_mode'] ?? PricingMode::PerPerson->value),
            privateEnabled: (bool) ($data['private_enabled'] ?? false),
            groupEnabled: (bool) ($data['group_enabled'] ?? false),
            minPersons: (int) ($data['min_persons'] ?? 1),
            maxPersons: isset($data['max_persons']) && $data['max_persons'] !== '' ? (int) $data['max_persons'] : null,
            basePrice: isset($data['base_price']) && $data['base_price'] !== '' ? (float) $data['base_price'] : null,
            baseChildPrice: isset($data['base_child_price']) && $data['base_child_price'] !== '' ? (float) $data['base_child_price'] : null,
            depositPercent: (float) ($data['deposit_percent'] ?? 30),
            status: PublishStatus::from($data['status'] ?? PublishStatus::Draft->value),
            attributes: array_diff_key($data, array_flip($known)),
            unitOptions: isset($data['unit_options']) ? array_values(array_map(UnitOptionData::fromArray(...), $data['unit_options'])) : null,
            privateTiers: isset($data['private_tiers']) ? array_values(array_map(PrivateTierData::fromArray(...), $data['private_tiers'])) : null,
            availability: isset($data['availability']) ? self::availabilityFrom($data['availability']) : null,
            days: $days !== null ? array_map(fn (array $day, int $index): TripDayData => TripDayData::fromArray($day, $index + 1), $days, array_keys($days)) : null,
            addonIds: isset($data['addon_ids']) ? array_values(array_map('intval', $data['addon_ids'])) : null,
        );
    }

    /**
     * @param  array<string, array<string, mixed>>  $availability  keyed by booking type value
     * @return list<AvailabilityRuleData>
     */
    private static function availabilityFrom(array $availability): array
    {
        $rules = [];

        foreach (BookingType::cases() as $bookingType) {
            if (isset($availability[$bookingType->value])) {
                $rules[] = AvailabilityRuleData::fromArray($bookingType, $availability[$bookingType->value]);
            }
        }

        return $rules;
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
            'type' => $this->type,
            'destination_id' => $this->destinationId,
            'title' => $this->title,
            'slug' => $this->slug,
            'pricing_mode' => $this->pricingMode,
            'private_enabled' => $this->privateEnabled,
            'group_enabled' => $this->groupEnabled,
            'min_persons' => $this->minPersons,
            'max_persons' => $this->maxPersons,
            'base_price' => $this->basePrice,
            'base_child_price' => $this->baseChildPrice,
            'deposit_percent' => $this->depositPercent,
            'status' => $this->status,
        ];
    }
}
