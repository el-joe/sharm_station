<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Database\Factories\ChildPolicyFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphTo;

#[Fillable(['owner_type', 'owner_id', 'children_allowed', 'min_child_age', 'adult_from_age', 'max_discounted_children', 'notes'])]
class ChildPolicy extends Model
{
    /** @use HasFactory<ChildPolicyFactory> */
    use HasFactory, HasTranslations;

    /**
     * @var list<string>
     */
    protected array $translatable = ['notes'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'children_allowed' => 'boolean',
            'min_child_age' => 'integer',
            'adult_from_age' => 'integer',
            'max_discounted_children' => 'integer',
        ];
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function owner(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * @return HasMany<ChildAgeBand, $this>
     */
    public function bands(): HasMany
    {
        return $this->hasMany(ChildAgeBand::class)->orderBy('min_age');
    }

    public function bandForAge(int $age): ?ChildAgeBand
    {
        return $this->bands->first(fn (ChildAgeBand $band): bool => $age >= $band->min_age && $age <= $band->max_age);
    }
}
