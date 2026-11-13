<?php

namespace App\Console\Commands;

use App\Services\SeasonPriceService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('prices:refresh-from')]
#[Description('Recompute the "from" price of trips, packages and rooms after seasons start or expire')]
class RefreshFromPrices extends Command
{
    public function handle(SeasonPriceService $seasons): int
    {
        $counts = $seasons->refreshAllFromPrices();

        $this->info("Refreshed {$counts['trips']} trips, {$counts['packages']} packages and {$counts['rooms']} rooms ({$counts['changed']} changed).");

        return self::SUCCESS;
    }
}
