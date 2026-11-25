<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

Schedule::command('reviews:sync-google')->twiceDaily(3, 15)->withoutOverlapping();
Schedule::command('prices:refresh-from')->dailyAt('00:10')->withoutOverlapping();

// Notifications and expiries (SRS 6.5, 10.1, 11, 15.1). Every job is idempotent: emails carry a dedupe key.
Schedule::command('bookings:process-holds')->everyMinute()->withoutOverlapping();
Schedule::command('bookings:expire-unpaid')->everyFifteenMinutes()->withoutOverlapping();
Schedule::command('bookings:balance-reminders')->dailyAt('08:00')->withoutOverlapping();
Schedule::command('bookings:overdue-balances')->dailyAt('08:30')->withoutOverlapping();
Schedule::command('reviews:send-invitations')->dailyAt('10:00')->withoutOverlapping();
Schedule::command('partners:send-statements')->monthlyOn(1, '07:00')->withoutOverlapping();
Schedule::command('currencies:refresh-rates')->hourly()->withoutOverlapping();
Schedule::command('bookings:mark-completed')->dailyAt('00:30')->withoutOverlapping();
Schedule::command('rooms:sync-ical')->everyThirtyMinutes()->withoutOverlapping();
