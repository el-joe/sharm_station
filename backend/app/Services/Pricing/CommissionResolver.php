<?php

namespace App\Services\Pricing;

use App\DTOs\Pricing\CommissionData;
use App\Enums\CommissionCategory;
use App\Enums\PriceRule;
use App\Models\Partner;
use App\Repositories\Contracts\CommissionSettingRepositoryInterface;

/**
 * Commission resolution (SRS 8.1): partner override for the type → global setting → 0 %.
 * Accommodation (null category) never has a commission.
 */
class CommissionResolver
{
    /**
     * @var array<string, float>|null
     */
    private ?array $globals = null;

    public function __construct(private readonly CommissionSettingRepositoryInterface $settings) {}

    public function resolve(?Partner $partner, ?CommissionCategory $category): CommissionData
    {
        if ($category === null) {
            return CommissionData::none();
        }

        if ($partner !== null) {
            $partner->loadMissing('categoryCommissions');
            $override = $partner->commissionFor($category);

            if ($override !== null) {
                return new CommissionData($override, PriceRule::PartnerOverride, $category);
            }
        }

        $global = $this->globalPercents()[$category->value] ?? null;

        if ($global !== null) {
            return new CommissionData($global, PriceRule::GlobalSetting, $category);
        }

        return CommissionData::none($category);
    }

    /**
     * Effective commission per type for a partner (dashboard, profile, admin).
     *
     * @return list<array{category: string, label: string, percent: float, source: string, standard_percent: float|null, override_percent: float|null}>
     */
    public function effectiveFor(?Partner $partner): array
    {
        $partner?->loadMissing('categoryCommissions');

        return array_map(function (CommissionCategory $category) use ($partner): array {
            $resolved = $this->resolve($partner, $category);

            return [
                'category' => $category->value,
                'label' => $category->label(),
                'percent' => $resolved->percent,
                'source' => $resolved->source->value,
                'standard_percent' => $this->globalPercents()[$category->value] ?? null,
                'override_percent' => $partner?->commissionFor($category),
            ];
        }, CommissionCategory::cases());
    }

    /**
     * @return array<string, float>
     */
    public function globalPercents(): array
    {
        return $this->globals ??= $this->settings->percents();
    }

    public function flush(): void
    {
        $this->globals = null;
    }
}
