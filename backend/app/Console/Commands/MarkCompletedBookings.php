<?php

namespace App\Console\Commands;

use App\Services\BookingService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('bookings:mark-completed')]
#[Description('Mark confirmed and fully paid bookings as completed the day after their service ended')]
class MarkCompletedBookings extends Command
{
    public function handle(BookingService $bookings): int
    {
        $this->info('Bookings completed: '.$bookings->markCompleted());

        return self::SUCCESS;
    }
}
