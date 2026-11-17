<?php

namespace App\Services;

use App\DTOs\Pricing\SeasonPriceData;
use App\Exceptions\DomainValidationException;
use App\Exceptions\SeasonOverlapException;
use App\Models\Contracts\Priceable;
use App\Models\NationalityGroup;
use App\Models\ProductPrice;
use App\Models\SeasonPrice;
use App\Repositories\Contracts\ProductPriceRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\DB;

/**
 * Admin price matrix (CR-10): base price per nationality group + seasons per group, saved together.
 */
class PriceMatrixService
{
    public function __construct(
        private readonly ProductPriceRepositoryInterface $productPrices,
        private readonly SeasonPriceService $seasons,
        private readonly NationalityService $nationalities,
    ) {}

    /**
     * Everything the price matrix tab renders.
     *
     * @param  Priceable&Model  $priceable
     * @return array{fields: array<string, string>, groups: list<NationalityGroup>, base: array<int, array<string, float|null>>, product_base: array<string, float|null>, seasons: Collection<int, SeasonPrice>}
     */
    public function editorData(Priceable $priceable): array
    {
        $fields = $priceable->priceFields();
        $prices = $this->productPrices->allFor($priceable)->keyBy('nationality_group_id');
        $base = [];

        foreach ($this->nationalities->groups() as $group) {
            /** @var ProductPrice|null $row */
            $row = $prices->get($group->id);

            foreach (array_keys($fields) as $field) {
                $base[$group->id][$field] = $row?->priceFor($field);
            }
        }

        return [
            'fields' => $fields,
            'groups' => $this->nationalities->groups()->all(),
            'base' => $base,
            'product_base' => array_map(fn (string $field): ?float => $priceable->basePriceFor($field), array_combine(array_keys($fields), array_keys($fields)) ?: []),
            'seasons' => $this->seasons->allFor($priceable),
        ];
    }

    /**
     * Saves the base price per group and replaces the seasons in one transaction.
     *
     * @param  Priceable&Model  $priceable
     * @param  array<int, array<string, mixed>>  $base  group id => [adult_price, child_price, tier_prices => [id => price], unit_prices, price_per_night, price]
     * @param  list<array{id: int|null, data: SeasonPriceData}>  $seasonRows
     *
     * @throws SeasonOverlapException
     * @throws DomainValidationException
     */
    public function save(Priceable $priceable, array $base, array $seasonRows): void
    {
        DB::transaction(function () use ($priceable, $base, $seasonRows): void {
            $kept = [];

            foreach ($base as $groupId => $values) {
                $attributes = $this->baseAttributes($values);

                if ($attributes === null) {
                    continue;
                }

                $this->productPrices->upsertFor($priceable, (int) $groupId, $attributes);
                $kept[] = (int) $groupId;
            }

            $this->productPrices->deleteOthers($priceable, $kept);
            $priceable->unsetRelation('productPrices');

            $this->seasons->sync($priceable, $seasonRows);
        });
    }

    /**
     * Cleans one group's base row; null when every cell is blank (the product base price applies).
     *
     * @param  array<string, mixed>  $values
     * @return array<string, mixed>|null
     */
    private function baseAttributes(array $values): ?array
    {
        $number = fn (mixed $value): ?float => $value === null || $value === '' ? null : round((float) $value, 2);
        $map = function (mixed $values) use ($number): ?array {
            $clean = array_filter(array_map($number, is_array($values) ? $values : []), fn (?float $value): bool => $value !== null);

            return $clean === [] ? null : $clean;
        };

        $attributes = [
            'adult_price' => $number($values['adult_price'] ?? null),
            'child_price' => $number($values['child_price'] ?? null),
            'price_per_night' => $number($values['price_per_night'] ?? null),
            'price' => $number($values['price'] ?? null),
            'tier_prices' => $map($values['tier_prices'] ?? null),
            'unit_prices' => $map($values['unit_prices'] ?? null),
        ];

        return array_filter($attributes, fn ($value): bool => $value !== null) === [] ? null : $attributes;
    }

    /**
     * Read-only price matrix for the MCP read tools, keyed by readable labels. Uses the loaded
     * productPrices / seasonPrices relations, so listings must eager load them.
     * A null group price means the product base price applies for that group.
     *
     * @param  Priceable&Model  $priceable
     * @return array{currency: string, product_base: array<string, float|null>, base_by_group: list<array<string, mixed>>, seasons: list<array<string, mixed>>}
     */
    public function summary(Priceable $priceable): array
    {
        $fields = $priceable->priceFields();
        $groups = $this->nationalities->groups()->keyBy('id');
        $prices = $priceable->productPrices->keyBy('nationality_group_id');
        $labelled = fn (callable $price): array => collect($fields)->mapWithKeys(fn (string $label, string $field): array => [$label => $price($field)])->all();

        return [
            'currency' => 'EUR',
            'product_base' => $labelled(fn (string $field): ?float => $priceable->basePriceFor($field)),
            'base_by_group' => $groups->values()->map(fn (NationalityGroup $group): array => [
                'group' => $group->code,
                'name' => (string) $group->translate('name', 'en'),
                'is_default' => (bool) $group->is_default,
                'prices' => $labelled(fn (string $field): ?float => $prices->get($group->id)?->priceFor($field)),
            ])->all(),
            'seasons' => $priceable->seasonPrices->map(fn (SeasonPrice $season): array => [
                'name' => $season->translate('name', 'en'),
                'from' => $season->starts_on?->toDateString(),
                'to' => $season->ends_on?->toDateString(),
                'recurring_yearly' => $season->recurring_yearly,
                'active' => $season->is_active,
                'group' => $season->nationality_group_id === null ? 'all' : ($groups->get($season->nationality_group_id)?->code ?? 'unknown'),
                'prices' => $labelled(fn (string $field): ?float => $season->priceFor($field)),
            ])->values()->all(),
        ];
    }
}
