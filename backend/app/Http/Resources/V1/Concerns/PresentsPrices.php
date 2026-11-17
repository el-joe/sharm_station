<?php

namespace App\Http\Resources\V1\Concerns;

use App\DTOs\Pricing\ResolvedPriceData;
use App\Enums\PricingMode;
use App\Models\Contracts\Priceable;
use App\Models\Package;
use App\Models\Room;
use App\Models\SeasonPrice;
use App\Models\Trip;
use App\Services\CurrencyService;
use App\Services\GoogleReviewService;
use App\Services\NationalityService;
use App\Services\Pricing\PriceResolver;
use App\Services\SeasonPriceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;

/**
 * Money formatting and nationality-group prices for public API resources.
 * Public resources never contain partner prices (CR-15), whoever is signed in.
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

    /**
     * Nationality group the request prices with (?nationality=, X-Nationality, profile; else the default group).
     */
    protected function nationalityGroupId(): int
    {
        return app(NationalityService::class)->currentGroup()->id;
    }

    /**
     * "Prices from" for the request's nationality group (falls back to the stored default-group price).
     */
    protected function groupFromPrice(Model $priceable, float|string|null $stored = null): ?array
    {
        return $this->money(app(SeasonPriceService::class)->groupFromPrice($priceable, $this->nationalityGroupId()) ?? $stored);
    }

    /**
     * Base customer price of one field for the request's group (no season).
     */
    protected function groupBasePrice(Priceable $priceable, string $field): ?array
    {
        return $this->money(app(PriceResolver::class)->base($priceable, $this->nationalityGroupId())->price($field));
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
     * Price list rows ("Prices and seasons" block) for the request's nationality group: its own seasons and
     * the all-groups ones. Blank season cells show the group's base price. Never contains partner prices.
     *
     * @return list<array<string, mixed>>
     */
    protected function priceList(Model $priceable): array
    {
        $groupId = $this->nationalityGroupId();
        $base = $priceable instanceof Priceable ? app(PriceResolver::class)->base($priceable, $groupId) : null;

        return app(SeasonPriceService::class)->priceList($priceable, null, $groupId)
            ->map(fn (array $row): array => [
                'id' => $row['season']->id,
                'name' => $row['season']->name,
                'nationality_group_id' => $row['season']->nationality_group_id,
                'starts_on' => $row['starts_on']->toDateString(),
                'ends_on' => $row['ends_on']->toDateString(),
                'recurring_yearly' => $row['season']->recurring_yearly,
                'is_current' => $row['is_current'],
                'prices' => $this->seasonPrices($priceable, $row['season'], $base),
            ])
            ->values()
            ->all();
    }

    /**
     * @return array<string, mixed>
     */
    private function seasonPrices(Model $priceable, SeasonPrice $season, ?ResolvedPriceData $base): array
    {
        $price = fn (string $field): ?array => $this->money($season->priceFor($field) ?? $base?->price($field));

        if ($priceable instanceof Room) {
            return ['price_per_night' => $price('price_per_night')];
        }

        if ($priceable instanceof Trip && $priceable->pricing_mode === PricingMode::PerUnit) {
            return [
                'units' => $priceable->unitOptions->map(fn ($option): array => [
                    'unit_option_id' => $option->id,
                    'name' => $option->name,
                    'seats' => $option->seats,
                    'price' => $price("unit_prices.{$option->id}"),
                ])->values()->all(),
            ];
        }

        $prices = [];

        if ($priceable instanceof Package || $priceable->group_enabled) {
            $prices['adult'] = $price('adult_price');
            $prices['child'] = $price('child_price');
        }

        if ($priceable instanceof Trip && $priceable->private_enabled) {
            $prices['private_tiers'] = $priceable->privateTiers->map(fn ($tier): array => [
                'tier_id' => $tier->id,
                'label' => $tier->label(),
                'min_people' => $tier->min_people,
                'max_people' => $tier->max_people,
                'price_per_person' => $price("tier_prices.{$tier->id}"),
            ])->values()->all();
        }

        return $prices;
    }
}
