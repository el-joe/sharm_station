<?php

namespace App\Console\Commands;

use App\Services\ScheduledNotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:expire-unpaid')]
#[Description('Expire trip, package and transfer bookings not paid by the payment deadline and email the booker')]
class ExpireUnpaidBookings extends Command
{
    public function handle(ScheduledNotificationService $scheduled): int
    {
        $this->info('Bookings expired: '.$scheduled->expireUnpaidBookings());

        return self::SUCCESS;
    }
}
