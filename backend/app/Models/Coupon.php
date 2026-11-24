<?php

namespace App\Models;

use App\Enums\CouponDiscountType;
use App\Enums\ServiceType;
use App\Models\Concerns\LogsActivity;
use Database\Factories\CouponFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable([
    'code', 'description', 'discount_type', 'value', 'valid_from', 'valid_to', 'usage_limit', 'usage_limit_per_customer',
    'minimum_total', 'services', 'partners_allowed', 'is_active',
])]
class Coupon extends Model
{
    /** @use HasFactory<CouponFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'discount_type' => CouponDiscountType::class,
            'value' => 'decimal:2',
            'valid_from' => 'immutable_date',
            'valid_to' => 'immutable_date',
            'usage_limit' => 'integer',
            'usage_limit_per_customer' => 'integer',
            'minimum_total' => 'decimal:2',
            'services' => 'array',
            'partners_allowed' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<CouponRedemption, $this>
     */
    public function redemptions(): HasMany
    {
        return $this->hasMany(CouponRedemption::class);
    }

    /**
     * Services the code applies to; empty means every service.
     *
     * @return list<ServiceType>
     */
    public function serviceTypes(): array
    {
        return array_values(array_filter(array_map(ServiceType::tryFrom(...), $this->services ?? [])));
    }

    public function appliesTo(ServiceType $service): bool
    {
        $services = $this->serviceTypes();

        return $services === [] || in_array($service, $services, true);
    }

    /**
     * Discount in EUR for a total, never more than the total itself.
     */
    public function discountFor(float $total): float
    {
        $discount = $this->discount_type === CouponDiscountType::Percent
            ? $total * min(100.0, (float) $this->value) / 100
            : (float) $this->value;

        return round(max(0.0, min($discount, $total)), 2);
    }
}
