<?php

namespace App\Http\Resources\V1\Concerns;

use App\Enums\CommissionCategory;
use App\Enums\PricingMode;
use App\Models\Package;
use App\Models\Partner;
use App\Models\Room;
use App\Models\SeasonPrice;
use App\Models\Trip;
use App\Services\CurrencyService;
use App\Services\GoogleReviewService;
use App\Services\PartnerPricingService;
use App\Services\SeasonPriceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Money formatting and partner awareness for API resources.
 * Partner prices are only ever added when the request comes from an approved partner.
 */
trait PresentsPrices
{
    /**
     * @return array{amount_eur: float, amount: float, currency: string}|null
     */
    protected function money(float|string|null $amountEur): ?array
    {
        return app(CurrencyService::class)->money($amountEur);
    }

    protected function partner(Request $request): ?Partner
    {
        $partner = $request->attributes->get('partner');

        return $partner instanceof Partner ? $partner : null;
    }

    /**
     * Business-level Google rating (CR-4: Google reviews replace on-site reviews), cached for the request.
     *
     * @return array{rating: float|null, reviews_count: int}
     */
    protected function googleRating(Request $request): array
    {
        if (! $request->attributes->has('google_rating')) {
            $service = app(GoogleReviewService::class);
            $rating = $service->rating($service->defaultPlaceId());
            $request->attributes->set('google_rating', ['rating' => $rating['rating'] ?? null, 'reviews_count' => $rating['total'] ?? 0]);
        }

        return $request->attributes->get('google_rating');
    }

    /**
     * @return array{amount_eur: float, amount: float, currency: string}|null
     */
    protected function partnerMoney(Request $request, CommissionCategory $category, float|string|null $retail, float|string|null $explicit = null): ?array
    {
        $partner = $this->partner($request);

        if ($partner === null || $retail === null) {
            return null;
        }

        return $this->money(app(PartnerPricingService::class)->unitPrice($partner, $category, (float) $retail, $explicit));
    }

    /**
     * Price list rows ("Prices and seasons" block). Never contains partner prices.
     *
     * @return list<array<string, mixed>>
     */
    protected function priceList(Model $priceable): array
    {
        return app(SeasonPriceService::class)->priceList($priceable)
            ->map(fn (array $row): array => [
                'id' => $row['season']->id,
                'name' => $row['season']->name,
                'starts_on' => $row['starts_on']->toDateString(),
                'ends_on' => $row['ends_on']->toDateString(),
                'recurring_yearly' => $row['season']->recurring_yearly,
                'is_current' => $row['is_current'],
                'prices' => $this->seasonPrices($priceable, $row['season']),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function seasonPrices(Model $priceable, SeasonPrice $season): array
    {
        if ($priceable instanceof Room) {
            return ['price_per_night' => $this->money($season->price_per_night ?? $priceable->base_price)];
        }

        if ($priceable instanceof Trip && $priceable->pricing_mode === PricingMode::PerUnit) {
            return [
                'units' => $priceable->unitOptions->map(fn ($option): array => [
                    'unit_option_id' => $option->id,
                    'name' => $option->name,
                    'seats' => $option->seats,
                    'price' => $this->money($season->unitPriceFor($option->id) ?? $option->price),
                ])->values()->all(),
            ];
        }

        $prices = [];

        if ($priceable instanceof Package || $priceable->group_enabled) {
            $prices['adult'] = $this->money($season->adult_price ?? $priceable->base_price);
            $prices['child'] = $this->money($season->child_price ?? $priceable->base_child_price);
        }

        if ($priceable instanceof Trip && $priceable->private_enabled) {
            $prices['private_tiers'] = $priceable->privateTiers->map(fn ($tier): array => [
                'tier_id' => $tier->id,
                'label' => $tier->label(),
                'min_people' => $tier->min_people,
                'max_people' => $tier->max_people,
                'price_per_person' => $this->money($season->tierPriceFor($tier->id) ?? $tier->price_per_person),
            ])->values()->all();
        }

        return $prices;
    }
}
