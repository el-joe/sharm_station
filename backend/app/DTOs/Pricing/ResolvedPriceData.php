<?php

namespace App\DTOs\Pricing;

use App\Enums\PriceSource;
use App\Models\Contracts\Priceable;
use App\Models\ProductPrice;
use App\Models\SeasonPrice;

/**
 * Customer prices of one product for one nationality group on one date (PLAN v2.2 §3).
 * Every field resolves on its own: group season → all-groups season → group base → product base.
 */
final readonly class ResolvedPriceData
{
    public function __construct(
        public Priceable $priceable,
        public int $nationalityGroupId,
        public ?SeasonPrice $groupSeason,
        public ?SeasonPrice $allGroupsSeason,
        public ?ProductPrice $groupBase,
    ) {}

    public function price(string $field): ?float
    {
        return $this->resolve($field)[0];
    }

    public function source(string $field): ?PriceSource
    {
        return $this->resolve($field)[1];
    }

    public function adult(): ?float
    {
        return $this->price('adult_price');
    }

    public function child(): ?float
    {
        return $this->price('child_price');
    }

    public function tier(int $tierId): ?float
    {
        return $this->price("tier_prices.{$tierId}");
    }

    public function unit(int $unitOptionId): ?float
    {
        return $this->price("unit_prices.{$unitOptionId}");
    }

    public function night(): ?float
    {
        return $this->price('price_per_night');
    }

    /**
     * The season that applies on the date (the group's own season wins).
     */
    public function season(): ?SeasonPrice
    {
        return $this->groupSeason ?? $this->allGroupsSeason;
    }

    public function seasonName(): ?string
    {
        $season = $this->season();

        return $season !== null ? (string) $season->name : null;
    }

    /**
     * @return array{0: float|null, 1: PriceSource|null}
     */
    private function resolve(string $field): array
    {
        $layers = [
            [$this->groupSeason, PriceSource::GroupSeason],
            [$this->allGroupsSeason, PriceSource::AllGroupsSeason],
            [$this->groupBase, PriceSource::GroupBase],
        ];

        foreach ($layers as [$layer, $source]) {
            $value = $layer?->priceFor($field);

            if ($value !== null) {
                return [$value, $source];
            }
        }

        $base = $this->priceable->basePriceFor($field);

        return [$base, $base !== null ? PriceSource::ProductBase : null];
    }
}
