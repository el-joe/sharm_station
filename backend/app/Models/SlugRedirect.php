<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * A former slug of a trip, package, building or blog post (subject_type is the morph alias).
 */
#[Fillable(['subject_type', 'subject_id', 'old_slug'])]
class SlugRedirect extends Model
{
    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }
}
