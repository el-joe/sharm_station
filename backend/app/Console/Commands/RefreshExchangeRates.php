<?php

namespace App\Console\Commands;

use App\Services\CurrencyService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('currencies:refresh-rates {--force : Refresh now, ignoring the interval setting}')]
#[Description('Update automatic exchange rates from the API (manual rates and margins are kept)')]
class RefreshExchangeRates extends Command
{
    public function handle(CurrencyService $currencies): int
    {
        $result = $currencies->refreshRates((bool) $this->option('force'));

        if (! $result['refreshed']) {
            $this->line($result['reason'] ?? 'Not refreshed.');

            return self::SUCCESS;
        }

        $this->info('Rates updated: '.($result['updated'] === [] ? 'none' : implode(', ', $result['updated'])));

        return self::SUCCESS;
    }
}
