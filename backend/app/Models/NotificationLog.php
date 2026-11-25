<?php

namespace App\Models;

use App\Enums\NotificationEvent;
use App\Enums\NotificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One email in the sending log. "context" keeps the placeholder values and attachments so the email can be resent;
 * "dedupe_key" marks emails of scheduled jobs that must never be sent twice.
 *
 * context: {vars: array<string, string>, attachments?: {voucher?: int, statement?: {partner_id: int, period: string}}}
 */
#[Fillable(['event', 'locale', 'recipient', 'subject', 'status', 'error', 'related_type', 'related_id', 'context', 'dedupe_key', 'resent_from_id', 'sent_at'])]
class NotificationLog extends Model
{
    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'event' => NotificationEvent::class,
            'status' => NotificationStatus::class,
            'context' => 'array',
            'sent_at' => 'immutable_datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function related(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<self, $this>
     */
    public function resentFrom(): BelongsTo
    {
        return $this->belongsTo(self::class, 'resent_from_id');
    }
}
