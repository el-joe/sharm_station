<?php

namespace App\Models\Concerns;

use App\Models\ChildPolicy;
use Illuminate\Database\Eloquent\Relations\MorphOne;

trait HasChildPolicy
{
    /**
     * @return MorphOne<ChildPolicy, $this>
     */
    public function childPolicy(): MorphOne
    {
        return $this->morphOne(ChildPolicy::class, 'owner');
    }
}
