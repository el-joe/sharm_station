<?php

namespace App\Models;

use App\Models\Concerns\LogsActivity;
use Database\Factories\CurrencyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

#[Fillable(['code', 'name', 'symbol', 'rate_to_eur', 'margin_percent', 'is_enabled', 'is_manual_rate', 'sort'])]
class Currency extends Model
{
    /** @use HasFactory<CurrencyFactory> */
    use HasFactory, LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'rate_to_eur' => 'decimal:6',
            'margin_percent' => 'decimal:2',
            'is_enabled' => 'boolean',
            'is_manual_rate' => 'boolean',
        ];
    }

    /**
     * Units of this currency per 1 EUR, including the margin.
     */
    public function effectiveRate(): float
    {
        return (float) $this->rate_to_eur * (1 + (float) $this->margin_percent / 100);
    }
}
