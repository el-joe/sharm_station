<?php

namespace App\Models\Contracts;

/**
 * A product with a price matrix (base per nationality group + seasons): trip, package, room, transfer fixed route.
 *
 * Price fields are season_prices / product_prices columns ("adult_price", "child_price", "price_per_night",
 * "price") or map entries ("tier_prices.{tier id}", "unit_prices.{unit option id}").
 */
interface Priceable
{
    /**
     * The product's own (legacy) base price for a field, used when no group or season price is set.
     */
    public function basePriceFor(string $field): ?float;

    /**
     * Price fields of this product, in display order, with their labels (price matrix columns).
     *
     * @return array<string, string>
     */
    public function priceFields(): array;
}
