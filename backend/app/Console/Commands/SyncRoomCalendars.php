<?php

namespace App\Console\Commands;

use App\Services\IcalService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('rooms:sync-ical')]
#[Description('Import the external iCal calendars of every room (closed nights added and removed)')]
class SyncRoomCalendars extends Command
{
    public function handle(IcalService $ical): int
    {
        $result = $ical->syncAll();
        $this->info("Calendars synced: {$result['feeds']}, failed: {$result['failed']}");

        return self::SUCCESS;
    }
}
