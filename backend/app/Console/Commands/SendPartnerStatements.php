<?php

namespace App\Console\Commands;

use App\Services\ScheduledNotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('partners:send-statements')]
#[Description('Email last month\'s statement (PDF attached) to every active partner with bookings')]
class SendPartnerStatements extends Command
{
    public function handle(ScheduledNotificationService $scheduled): int
    {
        $this->info('Partner statements queued: '.$scheduled->sendPartnerStatements());

        return self::SUCCESS;
    }
}
