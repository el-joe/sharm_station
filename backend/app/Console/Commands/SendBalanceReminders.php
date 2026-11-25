<?php

namespace App\Console\Commands;

use App\Services\ScheduledNotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:balance-reminders')]
#[Description('Email the balance reminder N days before the due date (once per booking and due date)')]
class SendBalanceReminders extends Command
{
    public function handle(ScheduledNotificationService $scheduled): int
    {
        $this->info('Balance reminders queued: '.$scheduled->sendBalanceReminders());

        return self::SUCCESS;
    }
}
