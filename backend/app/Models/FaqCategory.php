<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
use Database\Factories\FaqCategoryFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['key', 'title', 'sort'])]
class FaqCategory extends Model
{
    /** @use HasFactory<FaqCategoryFactory> */
    use HasFactory, HasTranslations, LogsActivity;

    /**
     * @var list<string>
     */
    protected array $translatable = ['title'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return ['sort' => 'integer'];
    }

    /**
     * @return HasMany<FaqItem, $this>
     */
    public function items(): HasMany
    {
        return $this->hasMany(FaqItem::class)->orderBy('sort')->orderBy('id');
    }
}
