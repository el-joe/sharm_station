<?php

namespace App\Models;

use App\Enums\NotificationEvent;
use App\Models\Concerns\LogsActivity;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;

/**
 * Editable email template of one event in one language (SRS 14.1 Notifications).
 * Body is plain text with {{placeholders}}; blank lines separate paragraphs and **text** is bold.
 */
#[Fillable(['event', 'locale', 'subject', 'body', 'is_enabled'])]
class NotificationTemplate extends Model
{
    use LogsActivity;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => NotificationEvent::class,
            'is_enabled' => 'boolean',
        ];
    }
}
