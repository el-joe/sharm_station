<?php

namespace App\Models\Concerns;

use App\Models\Addon;
use Illuminate\Database\Eloquent\Relations\MorphToMany;

trait HasAddons
{
    /**
     * @return MorphToMany<Addon, $this>
     */
    public function addons(): MorphToMany
    {
        return $this->morphToMany(Addon::class, 'assignable', 'addon_assignments')
            ->withPivot('sort')
            ->withTimestamps()
            ->orderByPivot('sort');
    }
}
