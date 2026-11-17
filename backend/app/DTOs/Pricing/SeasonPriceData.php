<?php

namespace App\DTOs\Pricing;

use Carbon\CarbonImmutable;
use Illuminate\Foundation\Http\FormRequest;

final readonly class SeasonPriceData
{
    /**
     * @param  array<string, string>  $name  Translations, e.g. ['en' => 'High season', 'it' => 'Alta stagione'].
     * @param  array<int|string, mixed>|null  $unitPrices
     * @param  array<int|string, mixed>|null  $tierPrices
     */
    public function __construct(
        public array $name,
        public CarbonImmutable $startsOn,
        public CarbonImmutable $endsOn,
        public bool $recurringYearly = false,
        public bool $isActive = true,
        public ?float $adultPrice = null,
        public ?float $childPrice = null,
        public ?array $unitPrices = null,
        public ?array $tierPrices = null,
        public ?float $pricePerNight = null,
        public ?int $nationalityGroupId = null,
        public ?float $price = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        $nullableFloat = fn (string $key): ?float => isset($data[$key]) && $data[$key] !== '' ? (float) $data[$key] : null;

        return new self(
            name: is_array($data['name']) ? $data['name'] : ['en' => (string) $data['name']],
            startsOn: CarbonImmutable::parse($data['starts_on'])->startOfDay(),
            endsOn: CarbonImmutable::parse($data['ends_on'])->startOfDay(),
            recurringYearly: (bool) ($data['recurring_yearly'] ?? false),
            isActive: (bool) ($data['is_active'] ?? true),
            adultPrice: $nullableFloat('adult_price'),
            childPrice: $nullableFloat('child_price'),
            unitPrices: self::priceMap($data['unit_prices'] ?? null),
            tierPrices: self::priceMap($data['tier_prices'] ?? null),
            pricePerNight: $nullableFloat('price_per_night'),
            nationalityGroupId: isset($data['nationality_group_id']) && $data['nationality_group_id'] !== '' ? (int) $data['nationality_group_id'] : null,
            price: $nullableFloat('price'),
        );
    }

    /**
     * Drops options without a price, so blank editor cells fall back to the option's base price.
     *
     * @param  array<int|string, mixed>|null  $map
     * @return array<int|string, mixed>|null
     */
    private static function priceMap(?array $map): ?array
    {
        if ($map === null) {
            return null;
        }

        $clean = [];

        foreach ($map as $id => $value) {
            if (is_array($value)) {
                $value = $value['price'] ?? null;
            }

            if ($value !== null && $value !== '') {
                $clean[$id] = (float) $value;
            }
        }

        return $clean === [] ? null : $clean;
    }

    public static function fromRequest(FormRequest $request): self
    {
        return self::fromArray($request->validated());
    }

    public function displayName(): string
    {
        return $this->name[app()->getLocale()] ?? $this->name['en'] ?? (string) reset($this->name);
    }

    /**
     * @return array<string, mixed>
     */
    public function toAttributes(): array
    {
        return [
            'name' => $this->name,
            'starts_on' => $this->startsOn->toDateString(),
            'ends_on' => $this->endsOn->toDateString(),
            'recurring_yearly' => $this->recurringYearly,
            'is_active' => $this->isActive,
            'adult_price' => $this->adultPrice,
            'child_price' => $this->childPrice,
            'nationality_group_id' => $this->nationalityGroupId,
            'unit_prices' => $this->unitPrices,
            'tier_prices' => $this->tierPrices,
            'price_per_night' => $this->pricePerNight,
            'price' => $this->price,
        ];
    }
}
