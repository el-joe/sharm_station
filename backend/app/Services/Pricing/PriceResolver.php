<?php

namespace App\Services\Pricing;

use App\DTOs\Pricing\ResolvedPriceData;
use App\Enums\PackagePriceMode;
use App\Models\Contracts\Priceable;
use App\Models\Package;
use App\Models\ProductPrice;
use App\Repositories\Contracts\ProductPriceRepositoryInterface;
use App\Services\NationalityService;
use App\Services\SeasonPriceService;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;

/**
 * The one place customer prices are resolved (PLAN v2.2 §3, SRS 3.9):
 * season for the group on the date → season for all groups → base price of the group → product base price.
 * Rooms are resolved night by night by the caller.
 */
class PriceResolver
{
    public function __construct(
        private readonly SeasonPriceService $seasons,
        private readonly NationalityService $nationalities,
        private readonly ProductPriceRepositoryInterface $productPrices,
    ) {}

    /**
     * @param  Priceable&Model  $priceable
     */
    public function resolve(Priceable $priceable, ?int $nationalityGroupId, CarbonInterface $date): ResolvedPriceData
    {
        $groupId = $this->nationalities->resolveGroupId($nationalityGroupId);
        [$groupSeason, $allGroupsSeason] = $this->seasons->seasonsForDate($priceable, $date, $groupId);

        return new ResolvedPriceData($priceable, $groupId, $groupSeason, $allGroupsSeason, $this->groupBase($priceable, $groupId));
    }

    /**
     * Base prices of the group without any season (price matrix, "from" prices).
     *
     * @param  Priceable&Model  $priceable
     */
    public function base(Priceable $priceable, ?int $nationalityGroupId): ResolvedPriceData
    {
        $groupId = $this->nationalities->resolveGroupId($nationalityGroupId);

        return new ResolvedPriceData($priceable, $groupId, null, null, $this->groupBase($priceable, $groupId));
    }

    /**
     * Headline customer price of a product for a group: on a date the lowest priced field that day,
     * without a date the "from" price over the base and the current/future seasons. Child prices are ignored.
     *
     * @param  Priceable&Model  $priceable
     */
    public function lowestPrice(Priceable $priceable, ?int $nationalityGroupId, ?CarbonInterface $date = null): ?float
    {
        $groupId = $this->nationalities->resolveGroupId($nationalityGroupId);

        if ($date === null || ($priceable instanceof Package && $priceable->price_mode === PackagePriceMode::Computed)) {
            return $this->seasons->fromPrice($priceable, $date, $groupId);
        }

        return $this->lowestOf($this->resolve($priceable, $groupId, $date));
    }

    /**
     * Lowest priced field (child prices ignored) of resolved prices: the headline price of a season or base row.
     */
    public function lowestOf(ResolvedPriceData $resolved): ?float
    {
        $prices = [];

        foreach (array_keys(array_diff_key($resolved->priceable->priceFields(), ['child_price' => true])) as $field) {
            $price = $resolved->price($field);

            if ($price !== null && $price > 0) {
                $prices[] = $price;
            }
        }

        return $prices === [] ? null : round(min($prices), 2);
    }

    /**
     * @param  Priceable&Model  $priceable
     */
    public function groupBase(Priceable $priceable, int $groupId): ?ProductPrice
    {
        $prices = $priceable->relationLoaded('productPrices')
            ? $priceable->getRelation('productPrices')
            : $priceable->setRelation('productPrices', $this->productPrices->allFor($priceable))->getRelation('productPrices');

        return $prices->firstWhere('nationality_group_id', $groupId);
    }
}
