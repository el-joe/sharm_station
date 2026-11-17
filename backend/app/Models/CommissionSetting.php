<?php

namespace App\Models;

use App\Enums\CommissionCategory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Global partner commission percentage for one commission type (CR-11).
 */
#[Fillable(['category', 'percent'])]
class CommissionSetting extends Model
{
    protected $primaryKey = 'category';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => CommissionCategory::class,
            'percent' => 'decimal:2',
        ];
    }
}
