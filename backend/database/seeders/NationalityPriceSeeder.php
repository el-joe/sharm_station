<?php

namespace Database\Seeders;

use App\Models\Contracts\Priceable;
use App\Models\NationalityGroup;
use App\Models\Package;
use App\Models\ProductPrice;
use App\Models\Room;
use App\Models\SeasonPrice;
use App\Models\TransferFixedRoute;
use App\Models\Trip;
use App\Services\SeasonPriceService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Seeder;

/**
 * Demo price matrix (CR-10): a base price per nationality group for every product, cheaper seasons for
 * Egyptians and Arab countries, and a season for every group on the buggy safari and the Hotel Nile View rooms.
 * Egyptian is the cheapest group, Arab countries mid, Europe and Rest of world higher.
 */
class NationalityPriceSeeder extends Seeder
{
    /**
     * Factor applied to the product base (and season) prices per group code.
     */
    public const FACTORS = ['egyptian' => 0.6, 'arab_countries' => 0.8, 'europe' => 1.0, 'rest_of_world' => 1.1];

    /**
     * Groups that get their own copy of every all-groups season (Europe and Rest of world keep the shared seasons).
     */
    private const SEASONAL_GROUPS = ['egyptian', 'arab_countries'];

    public function run(): void
    {
        $groups = NationalityGroup::query()->whereIn('code', array_keys(self::FACTORS))->get()->keyBy('code');

        $products = [
            ...Trip::query()->with(['unitOptions', 'privateTiers', 'seasonPrices'])->get(),
            ...Package::query()->with('seasonPrices')->get(),
            ...Room::query()->with(['seasonPrices', 'building'])->get(),
            ...TransferFixedRoute::query()->with('seasonPrices')->get(),
        ];

        foreach ($products as $product) {
            $everyGroupSeason = ($product instanceof Trip && $product->slug === 'desert-buggy-safari')
                || ($product instanceof Room && $product->building?->slug === 'hotel-nile-view');

            $this->seedProduct($product, $groups->all(), $everyGroupSeason);
        }

        app(SeasonPriceService::class)->refreshAllFromPrices();
    }

    /**
     * @param  Priceable&Model  $product
     * @param  array<string, NationalityGroup>  $groups
     */
    private function seedProduct(Priceable $product, array $groups, bool $everyGroupSeason): void
    {
        $fields = array_keys($product->priceFields());
        $sharedSeasons = $product->seasonPrices->whereNull('nationality_group_id');

        foreach ($groups as $code => $group) {
            $factor = self::FACTORS[$code];
            $attributes = $this->scaled($fields, fn (string $field): ?float => $product->basePriceFor($field), $factor);

            if ($attributes !== []) {
                ProductPrice::query()->updateOrCreate(
                    ['priceable_type' => $product->getMorphClass(), 'priceable_id' => $product->getKey(), 'nationality_group_id' => $group->id],
                    $attributes,
                );
            }

            if (! $everyGroupSeason && ! in_array($code, self::SEASONAL_GROUPS, true)) {
                continue;
            }

            foreach ($sharedSeasons as $season) {
                /** @var SeasonPrice $season */
                $prices = $this->scaled($fields, fn (string $field): ?float => $season->priceFor($field), $factor);

                SeasonPrice::query()->updateOrCreate(
                    [
                        'priceable_type' => $product->getMorphClass(),
                        'priceable_id' => $product->getKey(),
                        'nationality_group_id' => $group->id,
                        'starts_on' => $season->starts_on->toDateString(),
                    ],
                    [
                        ...$prices,
                        'name' => $season->getTranslations('name'),
                        'ends_on' => $season->ends_on->toDateString(),
                        'recurring_yearly' => $season->recurring_yearly,
                        'is_active' => $season->is_active,
                    ],
                );
            }
        }
    }

    /**
     * Price attributes (columns and maps) scaled by the group factor and rounded to whole euros.
     *
     * @param  list<string>  $fields
     * @param  callable(string): ?float  $price
     * @return array<string, mixed>
     */
    private function scaled(array $fields, callable $price, float $factor): array
    {
        $attributes = [];

        foreach ($fields as $field) {
            $value = $price($field);

            if ($value === null) {
                continue;
            }

            $value = max(1, round($value * $factor));

            if (str_contains($field, '.')) {
                [$map, $id] = explode('.', $field, 2);
                $attributes[$map][(int) $id] = $value;
            } else {
                $attributes[$field] = $value;
            }
        }

        return $attributes;
    }
}
