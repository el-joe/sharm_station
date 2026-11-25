<?php

namespace App\Console\Commands;

use App\Services\ScheduledNotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:process-holds')]
#[Description('Warn customers before an accommodation hold expires, then expire unpaid holds and release the rooms')]
class ProcessAccommodationHolds extends Command
{
    public function handle(ScheduledNotificationService $scheduled): int
    {
        $result = $scheduled->processAccommodationHolds();
        $this->info("Hold warnings: {$result['warned']}, expired: {$result['expired']}");

        return self::SUCCESS;
    }
}
