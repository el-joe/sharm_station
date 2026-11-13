<?php

namespace App\Services;

use App\DTOs\Pricing\PartnerPriceData;
use App\Enums\CommissionCategory;
use App\Enums\PriceRule;
use App\Models\Partner;
use App\Models\User;

/**
 * Partner price priority (SRS 8.1): item partner price > partner category commission > partner default commission > retail.
 */
class PartnerPricingService
{
    public function partnerFor(?User $user): ?Partner
    {
        $partner = $user?->activePartner();
        $partner?->loadMissing('categoryCommissions');

        return $partner;
    }

    /**
     * Rule to apply to one priced item, or null when the request is not from an active partner.
     */
    public function resolve(?Partner $partner, CommissionCategory $category, float|string|null $explicitPartnerPrice = null): ?PartnerPriceData
    {
        if ($partner === null || ! $partner->isActive()) {
            return null;
        }

        if ($explicitPartnerPrice !== null) {
            return new PartnerPriceData(PriceRule::PartnerPrice, explicitUnitPrice: (float) $explicitPartnerPrice);
        }

        $partner->loadMissing('categoryCommissions');
        $categoryCommission = $partner->commissionFor($category);

        if ($categoryCommission !== null) {
            return new PartnerPriceData(PriceRule::CategoryCommission, commissionPercent: $categoryCommission);
        }

        if ($partner->default_commission !== null) {
            return new PartnerPriceData(PriceRule::DefaultCommission, commissionPercent: (float) $partner->default_commission);
        }

        return new PartnerPriceData(PriceRule::Retail);
    }

    public function unitPrice(?Partner $partner, CommissionCategory $category, float $retailUnitPrice, float|string|null $explicitPartnerPrice = null): ?float
    {
        return $this->resolve($partner, $category, $explicitPartnerPrice)?->unitPrice($retailUnitPrice);
    }
}
