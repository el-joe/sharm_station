<?php

namespace Database\Factories;

use App\Enums\CommissionCategory;
use App\Models\Partner;
use App\Models\PartnerCategoryCommission;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<PartnerCategoryCommission>
 */
class PartnerCategoryCommissionFactory extends Factory
{
    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'partner_id' => Partner::factory(),
            'category' => CommissionCategory::SingleDayTrips,
            'commission_percent' => 15,
        ];
    }
}
