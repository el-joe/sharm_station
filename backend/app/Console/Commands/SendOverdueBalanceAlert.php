<?php

namespace App\Console\Commands;

use App\Services\ScheduledNotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:overdue-balances')]
#[Description('Daily email to the admin team listing bookings with an overdue balance')]
class SendOverdueBalanceAlert extends Command
{
    public function handle(ScheduledNotificationService $scheduled): int
    {
        $this->info('Overdue bookings listed: '.$scheduled->sendOverdueBalanceAlert());

        return self::SUCCESS;
    }
}
