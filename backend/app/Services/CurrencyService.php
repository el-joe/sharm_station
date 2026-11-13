<?php

namespace App\Services;

use App\DTOs\Currency\CurrencyRateData;
use App\Models\Currency;
use App\Repositories\Contracts\CurrencyRepositoryInterface;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

/**
 * Converts EUR amounts into the visitor's currency. Registered as a scoped singleton,
 * the current currency is set per request by the API middleware.
 */
class CurrencyService
{
    public const BASE = 'EUR';

    private string $current = self::BASE;

    /**
     * @var Collection<int, Currency>|null
     */
    private ?Collection $enabledCurrencies = null;

    public function __construct(private readonly CurrencyRepositoryInterface $currencies) {}

    /**
     * @return Collection<int, Currency>
     */
    public function enabled(): Collection
    {
        return $this->enabledCurrencies ??= $this->currencies->enabled();
    }

    public function isSupported(string $code): bool
    {
        $code = strtoupper($code);

        return $code === self::BASE || $this->enabled()->contains('code', $code);
    }

    public function setCurrent(string $code): void
    {
        $code = strtoupper($code);
        $this->current = $this->isSupported($code) ? $code : self::BASE;
    }

    public function current(): string
    {
        return $this->current;
    }

    /**
     * Units of $code per 1 EUR, margin included.
     */
    public function rate(?string $code = null): float
    {
        $code = strtoupper($code ?? $this->current);

        if ($code === self::BASE) {
            return 1.0;
        }

        $currency = $this->enabled()->firstWhere('code', $code);

        if ($currency === null) {
            throw new InvalidArgumentException("Currency [{$code}] is not enabled.");
        }

        return round($currency->effectiveRate(), 6);
    }

    public function convert(float $amountEur, ?string $code = null): float
    {
        return round($amountEur * $this->rate($code), 2);
    }

    public function toEur(float $amount, ?string $code = null): float
    {
        return round($amount / $this->rate($code), 2);
    }

    /**
     * @return array{amount_eur: float, amount: float, currency: string}|null
     */
    public function money(float|string|null $amountEur, ?string $code = null): ?array
    {
        if ($amountEur === null) {
            return null;
        }

        $code = strtoupper($code ?? $this->current);

        return [
            'amount_eur' => round((float) $amountEur, 2),
            'amount' => $this->convert((float) $amountEur, $code),
            'currency' => $code,
        ];
    }

    /**
     * Every currency for the admin table, in display order.
     *
     * @return Collection<int, Currency>
     */
    public function all(): Collection
    {
        return $this->currencies->query()->orderBy('sort')->orderBy('code')->get();
    }

    /**
     * Saves rates, margins and flags. EUR is the base: always enabled, rate 1, no margin.
     *
     * @param  list<CurrencyRateData>  $rows
     */
    public function updateRates(array $rows): void
    {
        DB::transaction(function () use ($rows): void {
            $currencies = $this->currencies->query()->whereKey(array_map(fn (CurrencyRateData $row): int => $row->id, $rows))->get()->keyBy('id');

            foreach ($rows as $row) {
                $currency = $currencies->get($row->id);

                if ($currency === null) {
                    continue;
                }

                $attributes = $currency->code === self::BASE
                    ? ['rate_to_eur' => 1, 'margin_percent' => 0, 'is_enabled' => true, 'is_manual_rate' => true]
                    : $row->toAttributes();

                $this->currencies->update($currency, $attributes);
            }
        });
    }
}
