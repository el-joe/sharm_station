<?php

namespace App\Services\Pricing;

use App\DTOs\Pricing\AddonSelectionData;
use App\DTOs\Pricing\QuoteLineData;
use App\DTOs\Pricing\QuoteRequestData;
use App\DTOs\Pricing\QuoteResultData;
use App\Enums\AddonPricingBasis;
use App\Enums\CommissionCategory;
use App\Enums\PriceRule;
use App\Enums\ServiceType;
use App\Models\Addon;
use App\Models\Partner;
use Illuminate\Support\Collection;

/**
 * Shared line, error and totals handling for the pricing engines.
 *
 * Requires $partnerPricing (PartnerPricingService) and $currencies (CurrencyService) on the using class.
 */
trait BuildsQuotes
{
    /**
     * @var array<string, list<string>>
     */
    private array $errors = [];

    private function resetErrors(): void
    {
        $this->errors = [];
    }

    private function addError(string $field, string $message): void
    {
        if (! in_array($message, $this->errors[$field] ?? [], true)) {
            $this->errors[$field][] = $message;
        }
    }

    private function nullableFloat(float|string|null $value): ?float
    {
        return $value === null ? null : (float) $value;
    }

    /**
     * Builds a priced line: retail rounded per line, partner price resolved by priority.
     *
     * @param  array<string, mixed>  $meta
     */
    private function line(string $type, string $label, int $quantity, float $unitPrice, ?float $explicitPartnerPrice, ?Partner $partner, CommissionCategory $category, array $meta = [], bool $commissionable = true): QuoteLineData
    {
        $unitPrice = round($unitPrice, 2);
        $total = round($unitPrice * $quantity, 2);
        $rule = $commissionable ? $this->partnerPricing->resolve($partner, $category, $explicitPartnerPrice) : null;

        if ($partner !== null && $rule === null) {
            return new QuoteLineData($type, $label, $quantity, $unitPrice, $total, $unitPrice, $total, PriceRule::Retail, $meta);
        }

        return new QuoteLineData(
            type: $type,
            label: $label,
            quantity: $quantity,
            unitPrice: $unitPrice,
            total: $total,
            partnerUnitPrice: $rule?->unitPrice($unitPrice),
            partnerTotal: $rule?->lineTotal($total, $quantity),
            priceRule: $rule?->rule,
            meta: $meta,
        );
    }

    /**
     * @param  list<QuoteLineData>  $lines
     * @param  array<string, mixed>  $context
     */
    private function buildResult(ServiceType $serviceType, array $lines, ?string $seasonName, ?Partner $partner, float $depositPercent, string $currency, array $context): QuoteResultData
    {
        $retailTotal = round(array_sum(array_map(fn (QuoteLineData $line): float => $line->total, $lines)), 2);
        $partnerTotal = null;
        $priceRule = PriceRule::Retail;

        if ($partner !== null) {
            $partnerTotal = round(array_sum(array_map(fn (QuoteLineData $line): float => (float) $line->partnerTotal, $lines)), 2);
            $rules = array_values(array_unique(array_map(
                fn (QuoteLineData $line): string => ($line->priceRule ?? PriceRule::Retail)->value,
                array_filter($lines, fn (QuoteLineData $line): bool => $line->type !== QuoteLineData::TYPE_TAX),
            )));
            $priceRule = match (count($rules)) {
                0 => PriceRule::Retail,
                1 => PriceRule::from($rules[0]),
                default => PriceRule::Mixed,
            };
        }

        $total = $partnerTotal ?? $retailTotal;
        $depositAmount = round($total * $depositPercent / 100, 2);
        $balanceAmount = round($total - $depositAmount, 2);
        $currency = $this->currencies->isSupported($currency) ? strtoupper($currency) : 'EUR';

        return new QuoteResultData(
            serviceType: $serviceType,
            lines: $lines,
            seasonName: $seasonName,
            retailTotal: $retailTotal,
            partnerTotal: $partnerTotal,
            commissionAmount: $partnerTotal !== null ? round($retailTotal - $partnerTotal, 2) : 0.0,
            priceRule: $priceRule,
            total: $total,
            depositPercent: $depositPercent,
            depositAmount: $depositAmount,
            balanceAmount: $balanceAmount,
            currency: $currency,
            exchangeRate: $this->currencies->rate($currency),
            converted: [
                'total' => $this->currencies->convert($total, $currency),
                'deposit' => $this->currencies->convert($depositAmount, $currency),
                'balance' => $this->currencies->convert($balanceAmount, $currency),
            ],
            errors: $this->errors,
            context: $context,
        );
    }

    /**
     * Required add-ons are always included; optional ones only when selected.
     *
     * @param  Collection<int, Addon>  $addons
     * @return list<QuoteLineData>
     */
    private function productAddonLines(Collection $addons, QuoteRequestData $request, int $persons, int $unitCount, ?Partner $partner, CommissionCategory $category, string $productLabel = 'trip'): array
    {
        $available = $addons->filter(fn (Addon $addon): bool => $addon->is_active)->keyBy('id');

        /** @var array<int, AddonSelectionData> $selected */
        $selected = [];

        foreach ($request->addons as $selection) {
            $addon = $available->get($selection->addonId);

            if ($addon === null) {
                $this->addError('addons', "Add-on #{$selection->addonId} is not available for this {$productLabel}.");

                continue;
            }

            if (! $addon->appliesTo($request->bookingType)) {
                $this->addError('addons', "{$addon->name} is not available for {$request->bookingType->label()} bookings.");

                continue;
            }

            $selected[$addon->id] = $selection;
        }

        $lines = [];

        foreach ($available as $addon) {
            if (! isset($selected[$addon->id]) && ! ($addon->required && $addon->appliesTo($request->bookingType))) {
                continue;
            }

            $requestedQuantity = $selected[$addon->id]->quantity ?? null;

            $quantity = match ($addon->pricing_basis) {
                AddonPricingBasis::PerPerson => $persons,
                AddonPricingBasis::PerBooking => 1,
                AddonPricingBasis::PerUnit => $requestedQuantity ?? max(1, $unitCount),
            };

            if ($addon->pricing_basis === AddonPricingBasis::PerUnit && ($quantity < 1 || $quantity > $addon->max_quantity)) {
                $this->addError('addons', "Choose between 1 and {$addon->max_quantity} × {$addon->name}.");

                continue;
            }

            $lines[] = $this->line(QuoteLineData::TYPE_ADDON, (string) $addon->name, $quantity, (float) $addon->price, $this->nullableFloat($addon->partner_price), $partner, $category, [
                'addon_id' => $addon->id,
                'pricing_basis' => $addon->pricing_basis->value,
                'required' => $addon->required,
            ]);
        }

        return $lines;
    }
}
