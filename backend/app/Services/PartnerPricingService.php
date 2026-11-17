<?php

namespace App\Services;

use App\DTOs\Pricing\CommissionData;
use App\Enums\CommissionCategory;
use App\Models\Partner;
use App\Models\User;
use App\Services\Pricing\CommissionResolver;

/**
 * Partner price = customer price − commission % (CR-11, CR-12). There are no explicit partner prices.
 */
class PartnerPricingService
{
    public function __construct(private readonly CommissionResolver $commissions) {}

    public function partnerFor(?User $user): ?Partner
    {
        $partner = $user?->activePartner();
        $partner?->loadMissing('categoryCommissions');

        return $partner;
    }

    public function commission(?Partner $partner, ?CommissionCategory $category): CommissionData
    {
        return $this->commissions->resolve($partner, $category);
    }

    public function partnerPrice(?Partner $partner, ?CommissionCategory $category, float $customerPrice): float
    {
        return $this->commission($partner, $category)->apply($customerPrice);
    }
}
