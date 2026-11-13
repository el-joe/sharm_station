<?php

namespace App\Models;

use App\Enums\CommissionCategory;
use Database\Factories\PartnerCategoryCommissionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['partner_id', 'category', 'commission_percent'])]
class PartnerCategoryCommission extends Model
{
    /** @use HasFactory<PartnerCategoryCommissionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => CommissionCategory::class,
            'commission_percent' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Partner, $this>
     */
    public function partner(): BelongsTo
    {
        return $this->belongsTo(Partner::class);
    }
}
