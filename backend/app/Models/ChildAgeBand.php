<?php

namespace App\Models;

use App\Enums\DiscountType;
use Database\Factories\ChildAgeBandFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['child_policy_id', 'min_age', 'max_age', 'discount_type', 'discount_value', 'sort'])]
class ChildAgeBand extends Model
{
    /** @use HasFactory<ChildAgeBandFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'min_age' => 'integer',
            'max_age' => 'integer',
            'discount_type' => DiscountType::class,
            'discount_value' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ChildPolicy, $this>
     */
    public function policy(): BelongsTo
    {
        return $this->belongsTo(ChildPolicy::class, 'child_policy_id');
    }

    /**
     * Child price derived from the adult price; never below zero.
     */
    public function applyTo(float $adultPrice): float
    {
        $value = (float) $this->discount_value;

        $price = match ($this->discount_type) {
            DiscountType::Free => 0.0,
            DiscountType::Percent => $adultPrice * (1 - $value / 100),
            DiscountType::FixedAmount => $adultPrice - $value,
            DiscountType::FixedPrice => $value,
        };

        return round(max(0.0, $price), 2);
    }

    public function label(): string
    {
        $range = $this->min_age.'-'.$this->max_age.' years';

        return match ($this->discount_type) {
            DiscountType::Free => $range.' (free)',
            DiscountType::Percent => $range.' ('.rtrim(rtrim((string) $this->discount_value, '0'), '.').'% off)',
            DiscountType::FixedAmount => $range.' (-EUR '.$this->discount_value.')',
            DiscountType::FixedPrice => $range,
        };
    }
}
