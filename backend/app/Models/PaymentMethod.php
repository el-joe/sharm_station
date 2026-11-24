<?php

namespace App\Models;

use App\Enums\PaymentMethodType;
use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
use Database\Factories\PaymentMethodFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Attributes\Scope;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;

/**
 * Online gateway (driver = fake, stripe...) or manual method with transfer details (SRS 10.2, 10.3).
 *
 * account_details: list of {label: string, value: string} shown to the customer.
 * currencies: list of ISO codes; empty or null = every currency.
 * credentials: encrypted gateway keys (never exposed by the API).
 */
#[Fillable([
    'code', 'type', 'driver', 'name', 'instructions', 'account_details', 'currencies', 'proof_required', 'surcharge_percent',
    'deadline_hours', 'logo', 'mode', 'credentials', 'is_enabled', 'sort',
])]
#[Hidden(['credentials'])]
class PaymentMethod extends Model
{
    /** @use HasFactory<PaymentMethodFactory> */
    use HasFactory, HasTranslations, LogsActivity;

    /**
     * @var list<string>
     */
    protected array $translatable = ['name', 'instructions'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => PaymentMethodType::class,
            'account_details' => 'array',
            'currencies' => 'array',
            'proof_required' => 'boolean',
            'surcharge_percent' => 'decimal:2',
            'deadline_hours' => 'integer',
            'credentials' => 'encrypted:array',
            'is_enabled' => 'boolean',
        ];
    }

    /**
     * @param  Builder<self>  $query
     */
    #[Scope]
    protected function enabled(Builder $query): void
    {
        $query->where('is_enabled', true);
    }

    public function supportsCurrency(string $currency): bool
    {
        return empty($this->currencies) || in_array(strtoupper($currency), $this->currencies, true);
    }

    public function isManual(): bool
    {
        return $this->type === PaymentMethodType::Manual;
    }
}
