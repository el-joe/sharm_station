<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use App\Models\Concerns\LogsActivity;
use Database\Factories\NationalityGroupFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A set of countries sharing the same prices (CR-10). Exactly one group is the default for unassigned countries.
 */
#[Fillable(['code', 'name', 'is_default', 'sort', 'is_active'])]
class NationalityGroup extends Model
{
    /** @use HasFactory<NationalityGroupFactory> */
    use HasFactory, HasTranslations, LogsActivity;

    /**
     * @var list<string>
     */
    protected array $translatable = ['name'];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_default' => 'boolean',
            'is_active' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Country, $this>
     */
    public function countries(): HasMany
    {
        return $this->hasMany(Country::class)->orderBy('sort');
    }

    /**
     * @return array{id: int, code: string, name: string, is_default: bool}
     */
    public function summary(): array
    {
        return ['id' => $this->id, 'code' => $this->code, 'name' => (string) $this->name, 'is_default' => $this->is_default];
    }
}
