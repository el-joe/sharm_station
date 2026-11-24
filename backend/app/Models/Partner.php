<?php

namespace App\Models;

use App\Enums\CommissionCategory;
use App\Enums\PartnerStatus;
use Database\Factories\PartnerFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['user_id', 'company', 'tax_number', 'country', 'website', 'phone', 'message', 'status', 'show_retail_price', 'coupons_allowed', 'approved_at', 'approved_by', 'rejection_reason', 'rejected_at', 'contact_name', 'notify_bookings', 'notify_statements', 'price_list_enabled'])]
class Partner extends Model
{
    /** @use HasFactory<PartnerFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PartnerStatus::class,
            'show_retail_price' => 'boolean',
            'coupons_allowed' => 'boolean',
            'notify_bookings' => 'boolean',
            'notify_statements' => 'boolean',
            'price_list_enabled' => 'boolean',
            'approved_at' => 'datetime',
            'rejected_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function approver(): BelongsTo
    {
        return $this->belongsTo(User::class, 'approved_by');
    }

    /**
     * @return HasMany<PartnerCategoryCommission, $this>
     */
    public function categoryCommissions(): HasMany
    {
        return $this->hasMany(PartnerCategoryCommission::class);
    }

    public function isActive(): bool
    {
        return $this->status === PartnerStatus::Active;
    }

    /**
     * The partner's own percentage for a type (override), or null when the global setting applies.
     */
    public function commissionFor(CommissionCategory $category): ?float
    {
        $commission = $this->categoryCommissions->firstWhere('category', $category);

        return $commission !== null ? (float) $commission->commission_percent : null;
    }

    /**
     * @return HasMany<Booking, $this>
     */
    public function bookings(): HasMany
    {
        return $this->hasMany(Booking::class);
    }
}
