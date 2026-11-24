<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
use Database\Factories\FaqItemFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * FAQ entry; "answer" is sanitized HTML per locale.
 */
#[Fillable(['faq_category_id', 'question', 'answer', 'sort', 'is_active'])]
class FaqItem extends Model
{
    /** @use HasFactory<FaqItemFactory> */
    use HasFactory, HasTranslations, LogsActivity;

    /**
     * @var list<string>
     */
    protected array $translatable = ['question', 'answer'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort' => 'integer', 'is_active' => 'boolean'];
    }

    /**
     * @return BelongsTo<FaqCategory, $this>
     */
    public function category(): BelongsTo
    {
        return $this->belongsTo(FaqCategory::class, 'faq_category_id');
    }
}
