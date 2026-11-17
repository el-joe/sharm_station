<?php

namespace App\Models;

use App\Enums\SuggestionStatus;
use App\Enums\SuggestionType;
use Database\Factories\AiSuggestionFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Laravel\Sanctum\PersonalAccessToken;

/**
 * A change proposed by Claude through the MCP server (CR-14). `payload` holds the proposed values,
 * `original` the subject's values when the suggestion was created (SEO). Nothing is applied until approved.
 */
#[Fillable([
    'type', 'subject_type', 'subject_id', 'payload', 'original', 'status', 'source',
    'created_by_token_id', 'created_by_user_id', 'reviewed_by', 'reviewed_at', 'review_note',
])]
class AiSuggestion extends Model
{
    /** @use HasFactory<AiSuggestionFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => SuggestionType::class,
            'status' => SuggestionStatus::class,
            'payload' => 'array',
            'original' => 'array',
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return BelongsTo<PersonalAccessToken, $this>
     */
    public function token(): BelongsTo
    {
        return $this->belongsTo(PersonalAccessToken::class, 'created_by_token_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function creator(): BelongsTo
    {
        return $this->belongsTo(User::class, 'created_by_user_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function isPending(): bool
    {
        return $this->status === SuggestionStatus::Pending;
    }

    /**
     * Short human title for lists and headers, e.g. "New building: Aldau Beach" or "Trip: Giftun Island snorkelling".
     */
    public function title(): string
    {
        $payload = $this->payload ?? [];

        if ($this->type === SuggestionType::AccommodationCreate) {
            return 'New building: '.($payload['building']['name']['en'] ?? 'Untitled');
        }

        $label = $payload['subject_label'] ?? null;

        return ($payload['subject_type_label'] ?? 'Product').': '.($label ?? '#'.$this->subject_id);
    }
}
