<?php

namespace App\Console\Commands;

use App\Services\ScheduledNotificationService;
use Illuminate\Console\Attributes\Description;
use Illuminate\Console\Attributes\Signature;
use Illuminate\Console\Command;

#[Signature('reviews:send-invitations')]
#[Description('Invite customers to review on Google the day after their service ended (once per booking)')]
class SendReviewInvitations extends Command
{
    public function handle(ScheduledNotificationService $scheduled): int
    {
        $this->info('Review invitations queued: '.$scheduled->sendReviewInvitations());

        return self::SUCCESS;
    }
}
