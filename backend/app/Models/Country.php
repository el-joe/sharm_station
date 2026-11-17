<?php

namespace App\Models;

use App\Models\Concerns\HasTranslations;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * ISO 3166-1 alpha-2 country with its nationality group (null = the default group applies).
 */
#[Fillable(['code', 'name', 'nationality_group_id', 'sort'])]
class Country extends Model
{
    use HasTranslations;

    protected $primaryKey = 'code';

    protected $keyType = 'string';

    public $incrementing = false;

    /**
     * @var list<string>
     */
    protected array $translatable = ['name'];

    /**
     * @return BelongsTo<NationalityGroup, $this>
     */
    public function nationalityGroup(): BelongsTo
    {
        return $this->belongsTo(NationalityGroup::class);
    }
}
