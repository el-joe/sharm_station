<?php

namespace App\Models\Concerns;

/**
 * unit_prices / tier_prices maps (option or tier id => price) shared by seasons and group base prices.
 */
trait HasPriceMaps
{
    public function unitPriceFor(int $unitOptionId): ?float
    {
        return $this->mapPrice($this->unit_prices, $unitOptionId);
    }

    public function tierPriceFor(int $tierId): ?float
    {
        return $this->mapPrice($this->tier_prices, $tierId);
    }

    /**
     * Price of one priced field ("adult_price", "child_price", "price_per_night", "price", "tier_prices.12", "unit_prices.3").
     */
    public function priceFor(string $field): ?float
    {
        if (str_contains($field, '.')) {
            [$map, $id] = explode('.', $field, 2);

            return $this->mapPrice($this->getAttribute($map), (int) $id);
        }

        $value = $this->getAttribute($field);

        return is_numeric($value) ? (float) $value : null;
    }

    /**
     * @param  array<int|string, mixed>|null  $map
     */
    private function mapPrice(?array $map, int $id): ?float
    {
        $value = $map[$id] ?? $map[(string) $id] ?? null;

        if (is_array($value)) {
            $value = $value['price'] ?? null;
        }

        return is_numeric($value) ? (float) $value : null;
    }
}
